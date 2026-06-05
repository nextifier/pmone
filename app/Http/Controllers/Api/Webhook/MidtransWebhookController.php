<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Services\Midtrans\MidtransService;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives Midtrans HTTP notifications (the "Payment Notification URL"
 * configured in the Midtrans dashboard).
 *
 * Mirrors XenditWebhookController: signature is verified inside the controller
 * (Midtrans signs with sha512(order_id + status_code + gross_amount + ServerKey)),
 * the reservation is resolved from `order_id` (= reservation_number), and the
 * reservation is mutated under a row lock. Everything that is not a genuine
 * signature failure returns HTTP 200 so Midtrans does not enter its retry cycle.
 */
class MidtransWebhookController extends Controller
{
    public function __construct(
        protected ReservationService $reservations,
    ) {}

    /**
     * Tolerant single-segment entry. Midtrans only needs the bare
     * `/api/webhooks/midtrans` URL; the optional `{segment}` variant lets a
     * dashboard misconfiguration (trailing path) still resolve instead of 404.
     */
    public function handleWithSegment(Request $request, string $segment): JsonResponse
    {
        return $this->handle($request);
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $orderId = (string) ($payload['order_id'] ?? '');
        if ($orderId === '') {
            return response()->json(['message' => 'Missing order_id (acknowledged)']);
        }

        // Retries reuse the reservation number with a `~N` suffix (Midtrans
        // rejects a duplicate non-final order_id), so strip it to find the owning
        // reservation. A tilde is used (not a hyphen) so this never truncates a
        // reservation_number whose own trailing segment is all digits, e.g.
        // "HTL-20260605-1234".
        $reservationNumber = (string) preg_replace('/~\d+$/', '', $orderId);

        $reservation = Reservation::query()
            ->where('reservation_number', $reservationNumber)
            ->first();

        if (! $reservation) {
            // Acknowledge with 200 so Midtrans does not retry — same rationale as
            // the Xendit handler (synthetic dashboard test pings, or a webhook
            // racing our create transaction).
            Log::warning('Midtrans webhook: reservation not found', ['order_id' => $orderId]);

            return response()->json(['message' => 'Reservation not found (acknowledged)']);
        }

        $gateway = $this->resolveGateway($reservation);

        if (! $gateway) {
            Log::warning('Midtrans webhook: no midtrans gateway to verify signature', [
                'reservation_number' => $reservationNumber,
            ]);

            return response()->json(['message' => 'No matching gateway (acknowledged)']);
        }

        $service = MidtransService::forGateway($gateway);

        if (! $service->verifySignature($payload)) {
            Log::warning('Midtrans webhook signature mismatch', [
                'reservation_number' => $reservationNumber,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $this->process($payload, $reservation, $service);
    }

    /**
     * Resolve the Midtrans gateway whose Server Key signed this notification.
     * Prefer the gateway that created the checkout (stored on the reservation);
     * fall back to the project's active Midtrans gateway in either mode.
     */
    private function resolveGateway(Reservation $reservation): ?ProjectPaymentGateway
    {
        $bound = $reservation->paymentGateway;
        if ($bound && $bound->provider === 'midtrans') {
            return $bound;
        }

        $project = $reservation->event?->project;
        if (! $project) {
            return null;
        }

        return $project->defaultPaymentGateway('midtrans', 'test')
            ?? $project->defaultPaymentGateway('midtrans', 'live');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function process(array $payload, Reservation $reservation, MidtransService $service): JsonResponse
    {
        $status = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraud = strtolower((string) ($payload['fraud_status'] ?? ''));

        return DB::transaction(function () use ($payload, $reservation, $service, $status, $fraud) {
            $locked = Reservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return response()->json(['message' => 'Reservation not found (acknowledged)']);
            }

            $isPaid = $status === 'settlement'
                || ($status === 'capture' && $fraud === 'accept');
            $isExpiry = in_array($status, ['expire', 'cancel', 'deny', 'failure'], true);
            $isRefund = in_array($status, ['refund', 'partial_refund'], true);

            if ($isPaid) {
                if ($locked->status->isPaid()) {
                    return response()->json(['message' => 'Reservation already paid']);
                }

                if ($locked->status->isFinal()) {
                    Log::warning('Midtrans webhook: paid event for final-state reservation', [
                        'reservation_id' => $locked->id,
                        'status' => $locked->status->value,
                    ]);

                    return response()->json(['message' => 'Reservation already in final state'], 409);
                }

                $this->reservations->markAsPaid($locked, [
                    'payment_channel' => $service->resolveChannel($payload),
                    'payment_destination' => $service->resolveDestination($payload),
                    'payment_id' => $payload['transaction_id'] ?? null,
                ]);

                activity()
                    ->performedOn($locked)
                    ->event('payment_paid')
                    ->withProperties([
                        'project_id' => $locked->event?->project_id,
                        'reservation_id' => $locked->id,
                        'amount' => $payload['gross_amount'] ?? null,
                        'transaction_id' => $payload['transaction_id'] ?? null,
                        'payment_type' => $payload['payment_type'] ?? null,
                        'transaction_status' => $status,
                    ])
                    ->log('Payment received via Midtrans');

                return response()->json(['message' => 'Reservation marked as paid']);
            }

            // Card capture flagged for manual fraud review — do not settle yet.
            if ($status === 'capture' && $fraud === 'challenge') {
                return response()->json(['message' => 'Payment under review (no action)']);
            }

            if ($isRefund) {
                return $this->handleRefund($payload, $locked);
            }

            if ($isExpiry) {
                if ($locked->status !== ReservationStatus::PendingPayment) {
                    return response()->json(['message' => 'Reservation not eligible for expiry']);
                }

                $this->reservations->expireReservation($locked);

                activity()
                    ->performedOn($locked)
                    ->event('payment_expired')
                    ->withProperties([
                        'project_id' => $locked->event?->project_id,
                        'reservation_id' => $locked->id,
                        'transaction_id' => $payload['transaction_id'] ?? null,
                        'transaction_status' => $status,
                    ])
                    ->log('Midtrans transaction '.$status);

                return response()->json(['message' => 'Reservation expired']);
            }

            // pending / authorize / unknown — acknowledge without action.
            return response()->json(['message' => 'Webhook received but no action taken']);
        });
    }

    /**
     * Minimal refund finalisation: flip a paid reservation to Refunded once,
     * idempotently. Auto-refund initiation (admin action) and richer
     * refund_amount/refund_id bookkeeping land in the refund phase.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleRefund(array $payload, Reservation $reservation): JsonResponse
    {
        if ($reservation->status === ReservationStatus::Refunded) {
            return response()->json(['message' => 'Refund already synced']);
        }

        if (! $reservation->status->isPaid()) {
            return response()->json(['message' => 'Reservation not eligible for refund sync']);
        }

        $reservation->update([
            'status' => ReservationStatus::Refunded,
            'refunded_at' => $reservation->refunded_at ?? now(),
        ]);

        activity()
            ->performedOn($reservation)
            ->event('refund_settled')
            ->withProperties([
                'project_id' => $reservation->event?->project_id,
                'reservation_id' => $reservation->id,
                'transaction_id' => $payload['transaction_id'] ?? null,
                'transaction_status' => $payload['transaction_status'] ?? null,
            ])
            ->log('Midtrans refund settled');

        return response()->json(['message' => 'Refund finalized synced']);
    }
}
