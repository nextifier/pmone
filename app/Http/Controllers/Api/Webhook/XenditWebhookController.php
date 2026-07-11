<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\ReservationStatus;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\TicketOrder;
use App\Services\Payment\PaymentGatewayResolver;
use App\Services\Reservation\ReservationService;
use App\Services\Ticket\TicketPurchaseService;
use App\Services\Xendit\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __construct(
        protected ReservationService $reservations,
        protected PaymentGatewayResolver $resolver,
        protected TicketPurchaseService $tickets,
    ) {}

    /**
     * Per-project webhook endpoint. The {project} segment in the URL identifies
     * which project's gateway should verify the signature. Each project registers
     * its own webhook URL in its Xendit dashboard.
     *
     * Dispatches to event-specific handlers based on payload shape:
     * - Invoice events: branched by `status` (PAID/SETTLED/EXPIRED)
     * - Refund events: identified by `event` field starting with `refund.`
     * - Payment Method events: logged for Phase 4 implementation
     * - Unknown events: 200 with "ignored" message (so Xendit does not retry)
     */
    /**
     * Tolerant single-segment entry. Xendit's legacy dashboard appends event
     * markers (`/invoice`, `/refund`, `/ewallet`, `/fva`, etc.) to the
     * configured base URL, and PM One originally supported per-project URLs
     * using project username as the segment. This handler accepts BOTH:
     *
     *   - If the segment matches an existing project username → per-project
     *     verify path (token compared against THAT project's gateway).
     *   - Otherwise → fall through to the generic handler which resolves the
     *     project from the payload's `external_id` / `invoice_id`.
     *
     * One entry point keeps Xendit dashboard configuration mistakes (typed
     * `/invoice` suffix vs project username vs nothing) from causing 404s.
     */
    public function invoiceWithSegment(Request $request, string $segment): JsonResponse
    {
        $project = Project::query()->where('username', $segment)->first();

        if ($project) {
            return $this->invoiceForProject($request, $project);
        }

        return $this->invoiceGeneric($request);
    }

    private function invoiceForProject(Request $request, Project $project): JsonResponse
    {
        $callbackToken = (string) $request->header('x-callback-token');
        $gateway = $callbackToken !== ''
            ? $this->resolver->resolveByWebhookToken($project, 'xendit', $callbackToken)
            : null;

        if (! $gateway) {
            Log::warning('Xendit webhook signature mismatch (per-project)', [
                'project' => $project->username,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $this->dispatch($request, $project);
    }

    /**
     * Generic webhook entry — used when several PM One projects share the
     * same Xendit account and therefore can only register ONE webhook URL
     * in the Xendit dashboard. Resolves the owning project from the payload:
     *   - Invoice events: `external_id` (= reservation_number).
     *   - Refund events: `invoice_id`, which Xendit NESTS under `data` —
     *     `data.invoice_id` — so both shapes must be probed.
     * Then enforces the same token check as the per-project route.
     */
    public function invoiceGeneric(Request $request): JsonResponse
    {
        $payload = $request->all();
        $callbackToken = (string) $request->header('x-callback-token');

        $project = $this->resolveProjectFromPayload($payload);

        // Refund payloads identify the reservation via `invoice_id`. Xendit
        // puts it inside the `data` object for refund.* events, but older
        // shapes had it top-level. Session events instead carry the session
        // id at `data.id`, which reservations store in `xendit_invoice_id`.
        $invoiceId = $payload['invoice_id']
            ?? $payload['data']['invoice_id']
            ?? $payload['data']['id']
            ?? $payload['id']
            ?? null;
        if (! $project && $invoiceId) {
            $reservation = Reservation::query()
                ->where('xendit_invoice_id', $invoiceId)
                ->first();
            $project = $reservation?->event?->project;
        }

        // QR Code events (qr.payment / qr.refund) carry no external_id or
        // invoice_id — resolve the project from the QR payment / refund ids.
        if (! $project) {
            $project = $this->resolveProjectFromQrPayload($payload);
        }

        if (! $project) {
            Log::warning('Xendit webhook (generic) could not resolve project', [
                'external_id' => $payload['external_id'] ?? null,
                'invoice_id' => $invoiceId,
                'event' => $payload['event'] ?? null,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Reservation not found (acknowledged)']);
        }

        $gateway = $callbackToken !== ''
            ? $this->resolver->resolveByWebhookToken($project, 'xendit', $callbackToken)
            : null;

        if (! $gateway) {
            Log::warning('Xendit webhook signature mismatch (generic)', [
                'project' => $project->username,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $this->dispatch($request, $project);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveProjectFromPayload(array $payload): ?Project
    {
        // Invoice webhooks carry `external_id` at the root; Session webhooks
        // carry `reference_id` nested under `data`. Both equal the
        // reservation_number.
        $externalId = $payload['external_id']
            ?? $payload['data']['reference_id']
            ?? $payload['reference_id']
            ?? null;
        if (! $externalId) {
            return null;
        }

        $reservation = Reservation::query()
            ->where('reservation_number', $externalId)
            ->first();

        if ($reservation) {
            return $reservation->event?->project;
        }

        // Ticketing reuses the same per-project gateway + webhook URL; ticket
        // order numbers (TIX-) never collide with reservation numbers (HTL-).
        $order = TicketOrder::query()->where('order_number', $externalId)->first();

        return $order?->event?->project;
    }

    /**
     * Resolve the owning project for a QR Code webhook (qr.payment / qr.refund).
     * These payloads nest everything under `data` and have no external_id, so
     * we match on the QR payment id (`qrpy_`) / refund id (`qrrf_`) PM One
     * stores on the reservation, falling back to `reference_id`.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveProjectFromQrPayload(array $payload): ?Project
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        // qr.refund: `id` is the qrrf_ refund id, `qrpy_id` the QR payment id.
        // qr.payment: `id` is the qrpy_ payment id.
        $ids = array_filter([
            $data['qrpy_id'] ?? null,
            $data['id'] ?? null,
        ], fn ($id) => is_string($id) && $id !== '');

        foreach ($ids as $id) {
            $reservation = Reservation::query()
                ->where('xendit_payment_id', $id)
                ->orWhere('xendit_refund_id', $id)
                ->first();

            if ($reservation) {
                return $reservation->event?->project;
            }
        }

        $referenceId = $data['reference_id'] ?? null;
        if (is_string($referenceId) && $referenceId !== '') {
            $reservation = Reservation::query()
                ->where('reservation_number', $referenceId)
                ->first();

            return $reservation?->event?->project;
        }

        return null;
    }

    private function dispatch(Request $request, ?Project $project): JsonResponse
    {
        $payload = $request->all();
        $event = strtolower((string) ($payload['event'] ?? ''));

        // Refund events
        if (str_starts_with($event, 'refund.')) {
            return $this->handleRefundEvent($payload, $event);
        }

        // QR Code events. QRIS payments made through a Xendit invoice still
        // produce a QR payment object; refunding one emits `qr.refund` to the
        // "QR code paid & refunded" webhook URL.
        if ($event === 'qr.refund') {
            return $this->handleQrRefundEvent($payload);
        }

        // The matching invoice.paid event already settles the reservation, so
        // qr.payment needs no action — acknowledge so Xendit does not retry.
        if ($event === 'qr.payment') {
            return response()->json(['message' => 'QR payment acknowledged (settled via invoice)']);
        }

        // Payment Method events (saved tokens) - Phase 4 will implement
        if (str_starts_with($event, 'payment_method.')) {
            Log::info('Xendit payment_method webhook received (Phase 4 not implemented)', [
                'event' => $event,
                'project' => $project?->username,
                'payment_method_id' => $payload['id'] ?? null,
            ]);

            return response()->json(['message' => 'Acknowledged (no action)']);
        }

        // Payment Session events (Sessions API) - identified by the
        // `payment_session.` event prefix. The legacy invoice branch below
        // keys off a root-level `status`, which session payloads never carry
        // (their status lives under `data`), so the two never collide.
        if (str_starts_with($event, 'payment_session.')) {
            return $this->handleSessionEvent($payload, $event);
        }

        // Invoice events - identified by `status` field
        $status = strtolower((string) ($payload['status'] ?? ''));
        if (in_array($status, ['paid', 'settled', 'expired'], true)) {
            return $this->handleInvoiceEvent($payload, $status);
        }

        Log::info('Xendit webhook with unrecognized payload shape', [
            'event' => $event,
            'status' => $status,
            'project' => $project?->username,
        ]);

        return response()->json(['message' => 'Webhook received but no action taken']);
    }

    private function handleInvoiceEvent(array $payload, string $status): JsonResponse
    {
        $externalId = $payload['external_id'] ?? null;

        if (! $externalId) {
            return response()->json(['message' => 'Missing external_id'], 422);
        }

        return DB::transaction(function () use ($payload, $externalId, $status) {
            $reservation = Reservation::query()
                ->where('reservation_number', $externalId)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                // Not a reservation — try a ticket order (shared gateway + webhook).
                if ($ticketResponse = $this->handleTicketInvoiceEvent($externalId, $status, $payload)) {
                    return $ticketResponse;
                }

                // Acknowledge with 200 so Xendit does not retry. Common cases:
                //  - "Test and save" in the dashboard sends a synthetic
                //    external_id ("invoice_123124123") that will never match.
                //  - Webhook arrives milliseconds before our create
                //    transaction has committed (rare race).
                // Either way, a 4xx triggers Xendit's exponential-backoff
                // retry storm which buys us nothing — we log + move on.
                Log::warning('Xendit webhook: reservation not found', ['external_id' => $externalId]);

                return response()->json(['message' => 'Reservation not found (acknowledged)']);
            }

            if ($status === 'paid' || $status === 'settled') {
                if ($reservation->status->isPaid()) {
                    return response()->json(['message' => 'Reservation already paid']);
                }

                if ($reservation->status->isFinal()) {
                    Log::warning('Xendit webhook: paid event for final-state reservation', [
                        'reservation_id' => $reservation->id,
                        'status' => $reservation->status->value,
                    ]);

                    return response()->json(['message' => 'Reservation already in final state'], 409);
                }

                $this->reservations->markAsPaid($reservation, $payload);

                activity()
                    ->performedOn($reservation)
                    ->event('payment_paid')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'amount' => $payload['amount'] ?? null,
                        'invoice_id' => $payload['id'] ?? null,
                        'payment_method' => $payload['payment_method'] ?? null,
                        'payment_channel' => $payload['payment_channel'] ?? null,
                    ])
                    ->log('Payment received via Xendit');

                return response()->json(['message' => 'Reservation marked as paid']);
            }

            if ($status === 'expired') {
                if ($reservation->status !== ReservationStatus::PendingPayment) {
                    return response()->json(['message' => 'Reservation not eligible for expiry']);
                }

                $this->reservations->expireReservation($reservation);

                activity()
                    ->performedOn($reservation)
                    ->event('payment_expired')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'invoice_id' => $payload['id'] ?? null,
                    ])
                    ->log('Xendit invoice expired');

                return response()->json(['message' => 'Reservation expired']);
            }

            return response()->json(['message' => 'Webhook received but no action taken']);
        });
    }

    /**
     * Ticket-order counterpart of handleInvoiceEvent. Returns null when no
     * order matches the external id (so the caller falls through to its own
     * "not found" acknowledgement); otherwise applies paid/expired and returns
     * the response. Runs in its own locked transaction.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleTicketInvoiceEvent(string $externalId, string $status, array $payload): ?JsonResponse
    {
        $order = TicketOrder::query()
            ->where('order_number', $externalId)
            ->lockForUpdate()
            ->first();

        if (! $order) {
            return null;
        }

        if ($status === 'paid' || $status === 'settled') {
            if ($order->status === TicketOrderStatus::Confirmed) {
                return response()->json(['message' => 'Order already confirmed']);
            }

            // Trigger A: a genuine paid event landed after the order already
            // expired (slow bank transfer / retail settling after the 15-min
            // hard-expiry job released the seat). Do not hard-reject with 409 —
            // re-check live availability and either honor the payment or record
            // it for reconciliation (no oversell).
            if ($order->status === TicketOrderStatus::Expired) {
                return $this->settleTicketPaidAfterExpiry($order, $payload);
            }

            if ($order->status->isFinal()) {
                return response()->json(['message' => 'Order already in final state'], 409);
            }

            $this->tickets->markAsConfirmed($order, $payload);

            activity()
                ->performedOn($order)
                ->event('payment_paid')
                ->withProperties([
                    'project_id' => $order->event?->project_id,
                    'ticket_order_id' => $order->id,
                    'amount' => $payload['amount'] ?? null,
                    'invoice_id' => $payload['id'] ?? null,
                ])
                ->log('Ticket payment received via Xendit');

            return response()->json(['message' => 'Ticket order confirmed']);
        }

        if ($status === 'expired') {
            if ($order->status !== TicketOrderStatus::PendingPayment) {
                return response()->json(['message' => 'Order not eligible for expiry']);
            }

            $this->tickets->expireOrder($order);

            return response()->json(['message' => 'Ticket order expired']);
        }

        return response()->json(['message' => 'Webhook received but no action taken']);
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
                    'invoice_id' => $payload['id'] ?? null,
                ])
                ->log('Ticket payment received after expiry - stock still available, order re-confirmed');

            return response()->json(['message' => 'Ticket order re-confirmed after expiry']);
        }

        if ($outcome === 'needs_reconciliation') {
            return response()->json(['message' => 'Payment received after expiry but stock is no longer available; recorded for manual reconciliation']);
        }

        return response()->json(['message' => 'Order already in final state'], 409);
    }

    /**
     * Handle Xendit refund webhook events (refund.succeeded / refund.failed).
     *
     * Idempotent: matches reservation by `invoice_id` and only updates fields
     * that are still null/missing. Useful when refund settles asynchronously
     * (bank transfer can take 2-7 days).
     *
     * Xendit nests the refund fields (`id`, `invoice_id`, `amount`,
     * `failure_reason`) inside a `data` object for `refund.*` events. Older
     * payload shapes had them top-level — read `data` first, fall back to
     * the root so both are handled.
     */
    private function handleRefundEvent(array $payload, string $event): JsonResponse
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $invoiceId = $data['invoice_id'] ?? $payload['invoice_id'] ?? null;
        $payloadRefundId = $data['id'] ?? $payload['id'] ?? null;
        $refundAmount = $data['amount'] ?? $payload['amount'] ?? null;
        $failureReason = $data['failure_reason'] ?? $payload['failure_reason'] ?? null;

        if (! $invoiceId) {
            Log::info('Xendit refund webhook missing invoice_id', ['payload_keys' => array_keys($payload)]);

            return response()->json(['message' => 'Missing invoice_id (no action)']);
        }

        return DB::transaction(function () use ($event, $invoiceId, $payloadRefundId, $refundAmount, $failureReason) {
            $reservation = Reservation::query()
                ->where('xendit_invoice_id', $invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                // Ticketing reuses the same per-project gateway + webhook URL —
                // try a ticket order before giving up.
                $order = TicketOrder::query()
                    ->where('xendit_invoice_id', $invoiceId)
                    ->lockForUpdate()
                    ->first();

                if ($order) {
                    return $this->handleTicketRefundEvent($order, $event, $payloadRefundId, $refundAmount, $failureReason);
                }

                Log::warning('Xendit refund webhook: reservation not found', ['xendit_invoice_id' => $invoiceId]);

                return response()->json(['message' => 'Reservation not found (acknowledged)']);
            }

            if ($event === 'refund.failed') {
                Log::error('Xendit refund failed (webhook)', [
                    'reservation_id' => $reservation->id,
                    'reason' => $failureReason,
                ]);

                activity()
                    ->performedOn($reservation)
                    ->event('refund_failed')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'failure_reason' => $failureReason,
                        'refund_id' => $payloadRefundId,
                    ])
                    ->log('Xendit refund failed');

                return response()->json(['message' => 'Refund failure logged']);
            }

            // C5: Idempotency by xendit_refund_id (not refunded_at, which races with
            // ProcessXenditRefundJob async update).
            if (! empty($reservation->xendit_refund_id)
                && $payloadRefundId !== null
                && $reservation->xendit_refund_id === $payloadRefundId
            ) {
                return response()->json(['message' => 'Refund already synced']);
            }

            $update = [];
            if (empty($reservation->xendit_refund_id) && $payloadRefundId !== null) {
                $update['xendit_refund_id'] = $payloadRefundId;
            }
            if (empty($reservation->refunded_at)) {
                $update['refunded_at'] = now();
                $update['status'] = ReservationStatus::Refunded;
            }
            if (empty($reservation->refund_amount) && ! empty($refundAmount)) {
                $update['refund_amount'] = $refundAmount;
            }

            if (! empty($update)) {
                $reservation->update($update);

                activity()
                    ->performedOn($reservation)
                    ->event('refund_settled')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'refund_amount' => $update['refund_amount'] ?? $reservation->refund_amount,
                        'xendit_refund_id' => $update['xendit_refund_id'] ?? $reservation->xendit_refund_id,
                    ])
                    ->log('Xendit refund settled');
            }

            return response()->json(['message' => 'Refund finalized synced']);
        });
    }

    /**
     * Ticket-order counterpart of the reservation refund branch above. Only a
     * still-Confirmed order is settled to Refunded (voiding every attendee via
     * TicketPurchaseService::refundOrder); a redelivered event for an order
     * already Refunded is a no-op acknowledgement.
     */
    private function handleTicketRefundEvent(TicketOrder $order, string $event, ?string $payloadRefundId, mixed $refundAmount, ?string $failureReason): JsonResponse
    {
        if ($event === 'refund.failed') {
            Log::error('Xendit refund failed (webhook, ticket order)', [
                'ticket_order_id' => $order->id,
                'reason' => $failureReason,
            ]);

            activity()
                ->performedOn($order)
                ->event('refund_failed')
                ->withProperties([
                    'project_id' => $order->event?->project_id,
                    'ticket_order_id' => $order->id,
                    'failure_reason' => $failureReason,
                    'refund_id' => $payloadRefundId,
                ])
                ->log('Xendit refund failed');

            return response()->json(['message' => 'Refund failure logged']);
        }

        if ($order->status === TicketOrderStatus::Refunded) {
            return response()->json(['message' => 'Refund already synced']);
        }

        if ($order->status !== TicketOrderStatus::Confirmed) {
            Log::warning('Xendit refund webhook: ticket order not eligible for refund sync', [
                'ticket_order_id' => $order->id,
                'status' => $order->status->value,
            ]);

            return response()->json(['message' => 'Order not eligible for refund sync']);
        }

        $this->tickets->refundOrder($order, 'Refunded via Xendit webhook');

        activity()
            ->performedOn($order)
            ->event('refund_settled')
            ->withProperties([
                'project_id' => $order->event?->project_id,
                'ticket_order_id' => $order->id,
                'refund_amount' => $refundAmount,
                'xendit_refund_id' => $payloadRefundId,
            ])
            ->log('Xendit refund settled');

        return response()->json(['message' => 'Refund finalized synced']);
    }

    /**
     * Handle Xendit QR Code refund webhook (`qr.refund`).
     *
     * Xendit only emits this callback when a QR refund settles — there is no
     * "failed" QR refund callback in normal operation. The reservation is
     * matched by the refund id (`qrrf_`) PM One stored when it initiated the
     * refund, falling back to the QR payment id (`qrpy_`).
     *
     * Idempotent: ProcessXenditRefundJob already flips the reservation to
     * Refunded when it initiates the refund, so this webhook is normally a
     * confirmation. It still finalises the state as a backstop and records the
     * settlement in the activity log.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleQrRefundEvent(array $payload): JsonResponse
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $refundId = $data['id'] ?? null;          // qrrf_...
        $qrPaymentId = $data['qrpy_id'] ?? null;  // qrpy_...
        $status = strtoupper((string) ($data['status'] ?? ''));

        if (! $refundId && ! $qrPaymentId) {
            Log::info('Xendit qr.refund webhook missing identifiers', ['payload_keys' => array_keys($payload)]);

            return response()->json(['message' => 'Missing QR refund identifiers (no action)']);
        }

        return DB::transaction(function () use ($refundId, $qrPaymentId, $status) {
            $reservation = Reservation::query()
                ->where(function ($query) use ($refundId, $qrPaymentId) {
                    if ($refundId) {
                        $query->orWhere('xendit_refund_id', $refundId);
                    }
                    if ($qrPaymentId) {
                        $query->orWhere('xendit_payment_id', $qrPaymentId);
                    }
                })
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                Log::warning('Xendit qr.refund webhook: reservation not found', [
                    'xendit_refund_id' => $refundId,
                    'xendit_payment_id' => $qrPaymentId,
                ]);

                return response()->json(['message' => 'Reservation not found (acknowledged)']);
            }

            // A qr.refund callback should only ever touch a reservation already
            // in the refund flow. Guard against a stray event flipping a still
            // paid/active reservation.
            if (! in_array($reservation->status, [ReservationStatus::Cancelled, ReservationStatus::Refunded], true)) {
                Log::warning('Xendit qr.refund webhook for reservation not in refund flow', [
                    'reservation_id' => $reservation->id,
                    'status' => $reservation->status->value,
                ]);

                return response()->json(['message' => 'Reservation not in refund flow (acknowledged)']);
            }

            if ($status === 'FAILED') {
                Log::error('Xendit QR refund failed (webhook)', [
                    'reservation_id' => $reservation->id,
                    'xendit_refund_id' => $refundId,
                ]);

                activity()
                    ->performedOn($reservation)
                    ->event('refund_failed')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'xendit_refund_id' => $refundId,
                    ])
                    ->log('Xendit QR refund failed');

                return response()->json(['message' => 'QR refund failure logged']);
            }

            if ($reservation->status === ReservationStatus::Refunded && $reservation->refunded_at !== null) {
                return response()->json(['message' => 'QR refund already synced']);
            }

            $reservation->update([
                'status' => ReservationStatus::Refunded,
                'refunded_at' => now(),
                'xendit_refund_id' => $refundId ?? $reservation->xendit_refund_id,
            ]);

            activity()
                ->performedOn($reservation)
                ->event('refund_settled')
                ->withProperties([
                    'project_id' => $reservation->event?->project_id,
                    'reservation_id' => $reservation->id,
                    'xendit_refund_id' => $refundId ?? $reservation->xendit_refund_id,
                ])
                ->log('Xendit QR refund settled');

            return response()->json(['message' => 'QR refund settled']);
        });
    }

    /**
     * Handle Xendit Payment Session webhook events (payment_session.completed /
     * payment_session.expired).
     *
     * Mirrors handleInvoiceEvent() but reads the Sessions payload shape: the
     * session object lives under `data`, the reservation is identified by
     * `data.reference_id` (= reservation_number), and the session id is
     * `data.id` (a `ps-` value) which the reservation stores in
     * `xendit_invoice_id`.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleSessionEvent(array $payload, string $event): JsonResponse
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $referenceId = $data['reference_id'] ?? $payload['reference_id'] ?? null;
        $sessionId = $data['id'] ?? $payload['id'] ?? null;
        $status = strtoupper((string) ($data['status'] ?? ''));

        if (! $referenceId && ! $sessionId) {
            Log::info('Xendit session webhook missing identifiers', ['event' => $event]);

            return response()->json(['message' => 'Missing session identifiers (no action)']);
        }

        return DB::transaction(function () use ($data, $event, $referenceId, $sessionId, $status) {
            $reservation = Reservation::query()
                ->when(
                    $referenceId,
                    fn ($query) => $query->where('reservation_number', $referenceId),
                    fn ($query) => $query->where('xendit_invoice_id', $sessionId),
                )
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                // Ticketing shares the same per-project gateway + session webhook.
                // A ticket order paid via the Sessions API carries its
                // order_number (TIX-) as the session reference_id, so settle it
                // here — this is what lets ticket card payments capture the real
                // brand (resolveSessionChannel) just like reservations.
                if ($referenceId) {
                    $order = TicketOrder::query()
                        ->where('order_number', $referenceId)
                        ->lockForUpdate()
                        ->first();

                    if ($order) {
                        return $this->settleTicketSession($order, $data, $sessionId, $event, $status);
                    }
                }

                // Acknowledge with 200 so Xendit does not retry — same rationale
                // as handleInvoiceEvent (synthetic "Test and save" payloads, or
                // a webhook racing our create transaction).
                Log::warning('Xendit session webhook: reservation/order not found', [
                    'reference_id' => $referenceId,
                    'session_id' => $sessionId,
                ]);

                return response()->json(['message' => 'Reservation not found (acknowledged)']);
            }

            $isCompleted = $event === 'payment_session.completed' || $status === 'COMPLETED';
            $isExpired = $event === 'payment_session.expired' || $status === 'EXPIRED';

            if ($isCompleted) {
                if ($reservation->status->isPaid()) {
                    return response()->json(['message' => 'Reservation already paid']);
                }

                if ($reservation->status->isFinal()) {
                    Log::warning('Xendit session webhook: completed event for final-state reservation', [
                        'reservation_id' => $reservation->id,
                        'status' => $reservation->status->value,
                    ]);

                    return response()->json(['message' => 'Reservation already in final state'], 409);
                }

                $channel = $this->resolveSessionChannel($data, $reservation->paymentGateway);
                $this->reservations->markAsPaid(
                    $reservation,
                    $this->sessionPaidPayload($data, $sessionId, $reservation, $channel),
                );

                activity()
                    ->performedOn($reservation)
                    ->event('payment_paid')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'amount' => $data['amount'] ?? null,
                        'session_id' => $sessionId,
                        'payment_request_id' => $data['payment_request_id'] ?? null,
                    ])
                    ->log('Payment received via Xendit');

                return response()->json(['message' => 'Reservation marked as paid']);
            }

            if ($isExpired) {
                if ($reservation->status !== ReservationStatus::PendingPayment) {
                    return response()->json(['message' => 'Reservation not eligible for expiry']);
                }

                $this->reservations->expireReservation($reservation);

                activity()
                    ->performedOn($reservation)
                    ->event('payment_expired')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'session_id' => $sessionId,
                    ])
                    ->log('Xendit payment session expired');

                return response()->json(['message' => 'Reservation expired']);
            }

            // payment_session.canceled and any other sub-event: acknowledge only.
            Log::info('Xendit session webhook with no mapped action', [
                'event' => $event,
                'status' => $status,
                'reservation_id' => $reservation->id,
            ]);

            return response()->json(['message' => 'Webhook received but no action taken']);
        });
    }

    /**
     * Map a Xendit Payment Session `data` object into the array shape
     * ReservationService::markAsPaid() consumes.
     *
     * The session id is kept as the reservation's payment reference, and the
     * underlying payment request id is the closest Sessions-era equivalent of
     * the legacy `payment_id`. The channel is resolved separately (see
     * resolveSessionChannel) because the session payload never carries it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sessionPaidPayload(array $data, ?string $sessionId, Reservation $reservation, ?string $channel): array
    {
        $out = [
            'id' => $sessionId ?: $reservation->xendit_invoice_id,
        ];

        if (! empty($data['payment_request_id'])) {
            $out['payment_id'] = $data['payment_request_id'];
        }

        if ($channel !== null && $channel !== '') {
            $out['payment_channel'] = $channel;
        }

        return $out;
    }

    /**
     * Settle a ticket order from a Payment Session webhook — the ticketing
     * counterpart of the reservation branch in handleSessionEvent. Resolves the
     * real card brand the same way before confirming, so the Attendees Payment
     * column shows Visa/Mastercard/Amex rather than a generic "CREDIT_CARD".
     *
     * @param  array<string, mixed>  $data  The session object (webhook `data`).
     */
    private function settleTicketSession(
        TicketOrder $order,
        array $data,
        ?string $sessionId,
        string $event,
        string $status,
    ): JsonResponse {
        $isCompleted = $event === 'payment_session.completed' || $status === 'COMPLETED';
        $isExpired = $event === 'payment_session.expired' || $status === 'EXPIRED';

        if ($isCompleted) {
            if ($order->status === TicketOrderStatus::Confirmed) {
                return response()->json(['message' => 'Order already confirmed']);
            }

            if ($order->status->isFinal()) {
                return response()->json(['message' => 'Order already in final state'], 409);
            }

            $channel = $this->resolveSessionChannel($data, $order->paymentGateway);

            $payload = ['id' => $sessionId ?: $order->xendit_invoice_id];
            if ($channel !== null && $channel !== '') {
                $payload['payment_channel'] = $channel;
            }

            $this->tickets->markAsConfirmed($order, $payload);

            activity()
                ->performedOn($order)
                ->event('payment_paid')
                ->withProperties([
                    'project_id' => $order->event?->project_id,
                    'ticket_order_id' => $order->id,
                    'amount' => $data['amount'] ?? null,
                    'session_id' => $sessionId,
                    'payment_request_id' => $data['payment_request_id'] ?? null,
                    'payment_channel' => $channel,
                ])
                ->log('Ticket payment received via Xendit');

            return response()->json(['message' => 'Ticket order confirmed']);
        }

        if ($isExpired) {
            if ($order->status !== TicketOrderStatus::PendingPayment) {
                return response()->json(['message' => 'Order not eligible for expiry']);
            }

            $this->tickets->expireOrder($order);

            return response()->json(['message' => 'Ticket order expired']);
        }

        Log::info('Xendit session webhook (ticket) with no mapped action', [
            'event' => $event,
            'status' => $status,
            'ticket_order_id' => $order->id,
        ]);

        return response()->json(['message' => 'Webhook received but no action taken']);
    }

    /**
     * Resolve the payment channel (QRIS, OVO, a VA bank, ...) for a completed
     * Payment Session. The session.completed payload only lists
     * `allowed_payment_channels`, not the channel actually used, so this fetches
     * the underlying v3 payment request and normalises its `channel_code` to
     * the vocabulary the rest of the app keys on (receipt logos, reconciliation).
     *
     * Returns null when it cannot be resolved — the receipt then degrades to a
     * generic label rather than failing the webhook.
     *
     * @param  array<string, mixed>  $data  The session object (webhook `data`).
     */
    private function resolveSessionChannel(array $data, ?ProjectPaymentGateway $gateway): ?string
    {
        $paymentRequestId = $data['payment_request_id'] ?? null;

        if (! is_string($paymentRequestId) || $paymentRequestId === '' || ! $gateway) {
            return null;
        }

        $detail = XenditService::forGateway($gateway)->fetchPaymentRequestDetail($paymentRequestId);
        $code = $detail['channel_code'] ?? null;

        if (! is_string($code) || $code === '') {
            return null;
        }

        // v3 channel codes differ from the legacy/transaction codes the receipt
        // logo map keys on: cards arrive as "CARDS", virtual accounts as
        // "<BANK>_VIRTUAL_ACCOUNT". Normalise to the shared vocabulary.
        $code = strtoupper($code);

        if ($code === 'CARDS') {
            // The v3 payment_requests payload nests the card brand under
            // channel_properties.card_details. Field name varies by API
            // generation, so probe the usual suspects and fall back to the
            // generic CREDIT_CARD logo when nothing usable is returned.
            $cardDetails = data_get($detail, 'channel_properties.card_details', []);
            $brand = strtoupper((string) (
                ($cardDetails['network'] ?? null)
                ?? ($cardDetails['card_network'] ?? null)
                ?? ($cardDetails['brand'] ?? null)
                ?? ($cardDetails['card_brand'] ?? null)
                ?? data_get($detail, 'payment_method.card.network')
                ?? data_get($detail, 'payment_method.card.brand')
                ?? ''
            ));

            return match ($brand) {
                'VISA' => 'VISA',
                'MASTERCARD' => 'MASTERCARD',
                'JCB' => 'JCB',
                'AMEX', 'AMERICAN EXPRESS' => 'AMEX',
                default => 'CREDIT_CARD',
            };
        }

        return (string) preg_replace('/_VIRTUAL_ACCOUNT$/', '', $code);
    }
}
