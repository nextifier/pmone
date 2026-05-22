<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Xendit\XenditSdkException;

class ProcessXenditRefundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $reservationId,
        public float $amount,
        public string $reason,
    ) {}

    public function handle(): void
    {
        $reservation = DB::transaction(function () {
            return Reservation::query()
                ->whereKey($this->reservationId)
                ->lockForUpdate()
                ->first();
        });

        if (! $reservation || ! $reservation->xendit_invoice_id) {
            return;
        }

        if ($reservation->xendit_refund_id) {
            Log::info('Xendit refund skipped (already refunded)', [
                'reservation_id' => $reservation->id,
                'xendit_refund_id' => $reservation->xendit_refund_id,
            ]);

            return;
        }

        $client = $this->resolveClient($reservation);

        // QRIS is refunded through Xendit's dedicated QR Code Refund endpoint,
        // not the unified Refund API — the latter rejects QRIS with
        // REFUND_NOT_SUPPORTED. The QR endpoint is keyed by the qrpy_ payment
        // id, which PM One stores on the reservation as xendit_payment_id.
        $isQris = strtoupper((string) $reservation->payment_channel) === 'QRIS';

        if ($isQris && ! $this->hasQrPaymentId($reservation)) {
            Log::error('Xendit QR refund cannot proceed — missing qrpy_ payment id', [
                'reservation_id' => $reservation->id,
                'xendit_payment_id' => $reservation->xendit_payment_id,
            ]);

            activity()
                ->performedOn($reservation)
                ->event('refund_failed')
                ->withProperties([
                    'project_id' => $reservation->event?->project_id,
                    'reservation_id' => $reservation->id,
                    'refund_amount' => $this->amount,
                    'refund_reason' => $this->reason,
                    'error' => 'Missing Xendit QR payment id (xendit_payment_id) for QRIS refund.',
                    'unrecoverable' => true,
                ])
                ->log('Xendit QR refund cannot be processed automatically — manual refund required');

            return;
        }

        try {
            if ($isQris) {
                // Returns immediately with status PENDING; the qr.refund webhook
                // confirms settlement later. Treated as initiated, consistent
                // with the unified-refund path below.
                $refund = $client->refundQrPayment(
                    (string) $reservation->xendit_payment_id,
                    $this->amount,
                    $this->reason,
                );
                $refundId = $refund['id'];
            } else {
                $refundId = $client->refundInvoice(
                    $reservation->xendit_invoice_id,
                    $this->amount,
                    $this->reason,
                );
            }

            $reservation->update([
                'status' => ReservationStatus::Refunded,
                'refunded_at' => now(),
                'refund_amount' => $this->amount,
                'refund_reason' => $this->reason,
                'xendit_refund_id' => $refundId,
            ]);

            activity()
                ->performedOn($reservation)
                ->event('refund_initiated')
                ->withProperties([
                    'project_id' => $reservation->event?->project_id,
                    'reservation_id' => $reservation->id,
                    'refund_amount' => $this->amount,
                    'refund_reason' => $this->reason,
                    'channel' => $isQris ? 'QRIS' : $reservation->payment_channel,
                    'xendit_refund_id' => $refundId,
                ])
                ->log('Refund initiated via Xendit');
        } catch (\Throwable $e) {
            // Surface the field-level detail Xendit returns. XenditSdkException's
            // getMessage() is generic; the raw body holds the real cause.
            // PaymentProviderException (raw QR refund call) already carries a
            // clean error code + message.
            $rawResponse = $this->rawResponseFor($e);

            // 4xx from Xendit means the request itself is bad — wrong channel,
            // unsupported QRIS issuer, invalid amount, expired refund window.
            // Retrying with the same payload will only fail again. 5xx + network
            // errors stay retryable.
            $status = $this->errorHttpStatus($e);
            $isUnrecoverable = $status >= 400 && $status < 500;

            Log::error('Xendit refund failed', [
                'reservation_id' => $reservation->id,
                'channel' => $reservation->payment_channel,
                'error' => $e->getMessage(),
                'xendit_response' => $rawResponse,
                'unrecoverable' => $isUnrecoverable,
            ]);

            activity()
                ->performedOn($reservation)
                ->event('refund_failed')
                ->withProperties([
                    'project_id' => $reservation->event?->project_id,
                    'reservation_id' => $reservation->id,
                    'refund_amount' => $this->amount,
                    'refund_reason' => $this->reason,
                    'channel' => $reservation->payment_channel,
                    'error' => $e->getMessage(),
                    'xendit_response' => $rawResponse,
                    'unrecoverable' => $isUnrecoverable,
                ])
                ->log($isUnrecoverable
                    ? 'Xendit refund cannot be processed automatically — manual refund required'
                    : 'Xendit refund failed (will retry)');

            if ($isUnrecoverable) {
                // Don't rethrow — queue would just retry the same losing request.
                // Reservation state (cancelled + refund_amount set + xendit_refund_id
                // null) already signals that the refund is outstanding for the admin
                // to handle manually.
                return;
            }

            throw $e;
        }
    }

    private function hasQrPaymentId(Reservation $reservation): bool
    {
        return is_string($reservation->xendit_payment_id)
            && str_starts_with($reservation->xendit_payment_id, 'qrpy');
    }

    /**
     * HTTP status behind a refund failure, normalised across the SDK exception
     * (unified refund) and PaymentProviderException (raw QR refund call).
     */
    private function errorHttpStatus(\Throwable $e): int
    {
        return match (true) {
            $e instanceof XenditSdkException => (int) $e->getStatus(),
            $e instanceof PaymentProviderException => $e->httpStatus,
            default => 0,
        };
    }

    /**
     * @return array<string, mixed>|object|string|null
     */
    private function rawResponseFor(\Throwable $e): array|object|string|null
    {
        return match (true) {
            $e instanceof XenditSdkException => $e->getRawResponse(),
            $e instanceof PaymentProviderException => [
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ],
            default => null,
        };
    }

    private function resolveClient(Reservation $reservation): XenditService
    {
        if ($reservation->payment_gateway_id) {
            $gateway = ProjectPaymentGateway::query()->find($reservation->payment_gateway_id);

            if ($gateway && $gateway->is_active) {
                return XenditService::forGateway($gateway);
            }
        }

        $project = $reservation->event?->project;
        $preferred = app()->environment('production') ? 'live' : 'test';
        $gateway = $project?->resolvePaymentGateway('xendit', $preferred);

        if (! $gateway) {
            throw new \RuntimeException(
                "Cannot refund reservation #{$reservation->id}: no active Xendit gateway found ".
                "(payment_gateway_id={$reservation->payment_gateway_id}, project={$project?->username})."
            );
        }

        return XenditService::forGateway($gateway);
    }
}
