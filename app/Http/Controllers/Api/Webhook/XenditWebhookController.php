<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Reservation;
use App\Services\Payment\PaymentGatewayResolver;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __construct(
        protected ReservationService $reservations,
        protected PaymentGatewayResolver $resolver,
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
    public function invoice(Request $request, Project $project): JsonResponse
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

    private function dispatch(Request $request, ?Project $project): JsonResponse
    {
        $payload = $request->all();
        $event = strtolower((string) ($payload['event'] ?? ''));

        // Refund events
        if (str_starts_with($event, 'refund.')) {
            return $this->handleRefundEvent($payload, $event);
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
                Log::warning('Xendit webhook: reservation not found', ['external_id' => $externalId]);

                return response()->json(['message' => 'Reservation not found'], 404);
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
     * Handle Xendit refund webhook events (refund.succeeded / refund.failed).
     *
     * Idempotent: matches reservation by `invoice_id` and only updates fields
     * that are still null/missing. Useful when refund settles asynchronously
     * (bank transfer can take 2-7 days).
     */
    private function handleRefundEvent(array $payload, string $event): JsonResponse
    {
        $invoiceId = $payload['invoice_id'] ?? $payload['data']['invoice_id'] ?? null;
        $payloadRefundId = $payload['id'] ?? null;

        if (! $invoiceId) {
            Log::info('Xendit refund webhook missing invoice_id', ['payload_keys' => array_keys($payload)]);

            return response()->json(['message' => 'Missing invoice_id (no action)']);
        }

        return DB::transaction(function () use ($payload, $event, $invoiceId, $payloadRefundId) {
            $reservation = Reservation::query()
                ->where('xendit_invoice_id', $invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                Log::warning('Xendit refund webhook: reservation not found', ['xendit_invoice_id' => $invoiceId]);

                return response()->json(['message' => 'Reservation not found']);
            }

            if ($event === 'refund.failed') {
                Log::error('Xendit refund failed (webhook)', [
                    'reservation_id' => $reservation->id,
                    'reason' => $payload['failure_reason'] ?? null,
                ]);

                activity()
                    ->performedOn($reservation)
                    ->event('refund_failed')
                    ->withProperties([
                        'project_id' => $reservation->event?->project_id,
                        'reservation_id' => $reservation->id,
                        'failure_reason' => $payload['failure_reason'] ?? null,
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
            if (empty($reservation->refund_amount) && ! empty($payload['amount'])) {
                $update['refund_amount'] = $payload['amount'];
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
}
