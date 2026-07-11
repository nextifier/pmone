<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\ReservationStatus;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\TicketOrder;
use App\Services\Midtrans\MidtransService;
use App\Services\Reservation\ReservationService;
use App\Services\Ticket\TicketPurchaseService;
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
        protected TicketPurchaseService $tickets,
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
            // Ticket orders share this webhook (same Midtrans account/gateway).
            // They are disambiguated by the `TIX-` prefix on the order number.
            $order = TicketOrder::query()
                ->where('order_number', $reservationNumber)
                ->first();

            if ($order) {
                return $this->handleTicketOrder($request, $payload, $order);
            }

            // Acknowledge with 200 so Midtrans does not retry — same rationale as
            // the Xendit handler (synthetic dashboard test pings, or a webhook
            // racing our create transaction).
            Log::warning('Midtrans webhook: reservation/order not found', ['order_id' => $orderId]);

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
     * Verify + process a Midtrans notification for a ticket order. Mirrors the
     * reservation path: resolve the Midtrans gateway, verify the signature, then
     * confirm or expire the order under a row lock.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleTicketOrder(Request $request, array $payload, TicketOrder $order): JsonResponse
    {
        $gateway = $this->resolveGatewayForTicket($order);

        if (! $gateway) {
            Log::warning('Midtrans webhook: no midtrans gateway to verify ticket signature', [
                'order_number' => $order->order_number,
            ]);

            return response()->json(['message' => 'No matching gateway (acknowledged)']);
        }

        $service = MidtransService::forGateway($gateway);

        if (! $service->verifySignature($payload)) {
            Log::warning('Midtrans webhook signature mismatch (ticket)', [
                'order_number' => $order->order_number,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $this->processTicket($payload, $order, $service);
    }

    private function resolveGatewayForTicket(TicketOrder $order): ?ProjectPaymentGateway
    {
        $bound = $order->paymentGateway;
        if ($bound && $bound->provider === 'midtrans') {
            return $bound;
        }

        $project = $order->event?->project;
        if (! $project) {
            return null;
        }

        return $project->defaultPaymentGateway('midtrans', 'test')
            ?? $project->defaultPaymentGateway('midtrans', 'live');
    }

    /**
     * Guard a ticket paid confirmation against an underpaid notification. A
     * gateway or misconfiguration may report a `gross_amount` smaller than the
     * order total; confirming on such a payload would issue tickets without
     * full payment.
     *
     * Only enforced when a positive amount is present — a missing/zero amount
     * is treated as "no amount to compare" and passes. A shortfall beyond a
     * 1 IDR epsilon is refused (caller must NOT confirm). Overpayment is
     * allowed but logged for reconciliation.
     *
     * @param  mixed  $rawAmount  The notification-reported gross amount.
     */
    private function ticketPaymentAmountSufficient(mixed $rawAmount, TicketOrder $order): bool
    {
        if ($rawAmount === null || $rawAmount === '') {
            return true;
        }

        $paid = (float) $rawAmount;
        if ($paid <= 0.0) {
            return true;
        }

        $total = (float) $order->total;
        $epsilon = 1.0;

        if ($paid + $epsilon < $total) {
            Log::warning('Midtrans webhook: payment_amount_mismatch (ticket order underpaid), confirmation skipped', [
                'ticket_order_id' => $order->id,
                'order_total' => $total,
                'paid_amount' => $paid,
            ]);

            return false;
        }

        if ($paid > $total + $epsilon) {
            Log::warning('Midtrans webhook: ticket order overpaid, confirming anyway', [
                'ticket_order_id' => $order->id,
                'order_total' => $total,
                'paid_amount' => $paid,
            ]);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processTicket(array $payload, TicketOrder $order, MidtransService $service): JsonResponse
    {
        $status = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraud = strtolower((string) ($payload['fraud_status'] ?? ''));

        return DB::transaction(function () use ($payload, $order, $service, $status, $fraud) {
            $locked = TicketOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return response()->json(['message' => 'Order not found (acknowledged)']);
            }

            $isPaid = $status === 'settlement'
                || ($status === 'capture' && $fraud === 'accept');
            $isExpiry = in_array($status, ['expire', 'cancel', 'deny', 'failure'], true);

            if ($isPaid) {
                if ($locked->status === TicketOrderStatus::Confirmed) {
                    return response()->json(['message' => 'Order already confirmed']);
                }

                if (! $this->ticketPaymentAmountSufficient($payload['gross_amount'] ?? null, $locked)) {
                    return response()->json(['message' => 'Payment amount mismatch (no action)']);
                }

                // markAsConfirmed flips PendingPayment -> Confirmed atomically and
                // is a no-op for any other state, so it is safe to call here.
                $this->tickets->markAsConfirmed($locked, [
                    'id' => $payload['transaction_id'] ?? $locked->xendit_invoice_id,
                    'payment_channel' => $service->resolveChannel($payload),
                ]);

                activity()
                    ->performedOn($locked)
                    ->event('payment_paid')
                    ->withProperties([
                        'project_id' => $locked->event?->project_id,
                        'ticket_order_id' => $locked->id,
                        'amount' => $payload['gross_amount'] ?? null,
                        'transaction_id' => $payload['transaction_id'] ?? null,
                        'payment_type' => $payload['payment_type'] ?? null,
                        'transaction_status' => $status,
                    ])
                    ->log('Ticket payment received via Midtrans');

                return response()->json(['message' => 'Ticket order confirmed']);
            }

            // Card capture flagged for manual fraud review — do not settle yet.
            if ($status === 'capture' && $fraud === 'challenge') {
                return response()->json(['message' => 'Payment under review (no action)']);
            }

            if ($isExpiry) {
                if ($locked->status !== TicketOrderStatus::PendingPayment) {
                    return response()->json(['message' => 'Order not eligible for expiry']);
                }

                $this->tickets->expireOrder($locked);

                activity()
                    ->performedOn($locked)
                    ->event('payment_expired')
                    ->withProperties([
                        'project_id' => $locked->event?->project_id,
                        'ticket_order_id' => $locked->id,
                        'transaction_id' => $payload['transaction_id'] ?? null,
                        'transaction_status' => $status,
                    ])
                    ->log('Midtrans ticket transaction '.$status);

                return response()->json(['message' => 'Ticket order expired']);
            }

            // pending / authorize / unknown — acknowledge without action.
            return response()->json(['message' => 'Webhook received but no action taken']);
        });
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
