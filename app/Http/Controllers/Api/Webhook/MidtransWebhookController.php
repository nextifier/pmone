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
            // Only a genuine `expire` releases stock. `cancel`/`deny`/`failure`
            // are non-terminal from Midtrans's perspective (a declined first
            // card attempt, an abandoned VA) — the order stays payable and the
            // buyer can retry the same checkout, so flipping to Expired here
            // would prematurely release the seat and dead-end a later retry.
            $isExpiry = in_array($status, ['expire'], true);

            if ($isPaid) {
                if ($locked->status === TicketOrderStatus::Confirmed) {
                    return response()->json(['message' => 'Order already confirmed']);
                }

                // Trigger A: a genuine paid event landed after the order already
                // expired (slow VA / retail settling after the 15-min hard-expiry
                // job released the seat). Re-check live availability and either
                // honor the payment or record it for reconciliation (no oversell).
                if ($locked->status === TicketOrderStatus::Expired) {
                    return $this->settleTicketPaidAfterExpiry($locked, [
                        'id' => $payload['transaction_id'] ?? $locked->xendit_invoice_id,
                        'payment_channel' => $service->resolveChannel($payload),
                        'amount' => $payload['gross_amount'] ?? null,
                    ]);
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

            if (in_array($status, ['refund', 'partial_refund'], true)) {
                return $this->handleTicketRefund($payload, $locked, $status);
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
     * Minimal ticket-order refund sync. A `refund` (full) settles Confirmed ->
     * Refunded and voids every attendee via TicketPurchaseService::refundOrder,
     * exactly like the Xendit refund branch. A `partial_refund` cannot be
     * mapped to specific attendees from this payload (no line-item detail), so
     * it is only logged + flagged for manual review — never auto-voids anyone.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleTicketRefund(array $payload, TicketOrder $order, string $status): JsonResponse
    {
        if ($status === 'partial_refund') {
            Log::warning('Midtrans partial refund for ticket order — needs manual attendee selection', [
                'ticket_order_id' => $order->id,
                'transaction_id' => $payload['transaction_id'] ?? null,
            ]);

            activity()
                ->performedOn($order)
                ->event('refund_needs_review')
                ->withProperties([
                    'project_id' => $order->event?->project_id,
                    'ticket_order_id' => $order->id,
                    'transaction_id' => $payload['transaction_id'] ?? null,
                    'transaction_status' => $status,
                ])
                ->log('Midtrans partial refund received - cannot auto-map to attendees, needs manual review');

            return response()->json(['message' => 'Partial refund received; flagged for manual review']);
        }

        if ($order->status === TicketOrderStatus::Refunded) {
            return response()->json(['message' => 'Refund already synced']);
        }

        if ($order->status !== TicketOrderStatus::Confirmed) {
            return response()->json(['message' => 'Order not eligible for refund sync']);
        }

        $this->tickets->refundOrder($order, 'Refunded via Midtrans webhook');

        activity()
            ->performedOn($order)
            ->event('refund_settled')
            ->withProperties([
                'project_id' => $order->event?->project_id,
                'ticket_order_id' => $order->id,
                'transaction_id' => $payload['transaction_id'] ?? null,
                'transaction_status' => $status,
            ])
            ->log('Midtrans refund settled');

        return response()->json(['message' => 'Refund finalized synced']);
    }

    /**
     * Trigger A: settle a paid event for a ticket order that already expired.
     * Delegates the no-oversell re-check + resurrect/record decision to
     * TicketPurchaseService::reconfirmAfterExpiry and logs accordingly.
     *
     * @param  array<string, mixed>  $payload
     */
    private function settleTicketPaidAfterExpiry(TicketOrder $order, array $payload): JsonResponse
    {
        $outcome = $this->tickets->reconfirmAfterExpiry($order, $payload);

        if ($outcome === 'reconfirmed') {
            activity()
                ->performedOn($order)
                ->event('payment_paid_after_expiry')
                ->withProperties([
                    'project_id' => $order->event?->project_id,
                    'ticket_order_id' => $order->id,
                    'amount' => $payload['amount'] ?? null,
                    'transaction_id' => $payload['id'] ?? null,
                ])
                ->log('Ticket payment received after expiry via Midtrans - stock still available, order re-confirmed');

            return response()->json(['message' => 'Ticket order re-confirmed after expiry']);
        }

        if ($outcome === 'needs_reconciliation') {
            return response()->json(['message' => 'Payment received after expiry but stock is no longer available; recorded for manual reconciliation']);
        }

        return response()->json(['message' => 'Order already in final state'], 409);
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
