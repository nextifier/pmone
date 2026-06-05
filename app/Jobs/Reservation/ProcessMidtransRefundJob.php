<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Services\Midtrans\MidtransService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mirror of {@see ProcessXenditRefundJob} for Midtrans. Midtrans keys the refund
 * by order_id (= reservation_number) via the Core API online/direct endpoint.
 * Channels that cannot be refunded online (most bank-transfer VAs) raise an
 * unrecoverable (4xx) error, which is logged for a manual refund rather than
 * retried. The Midtrans refund key is stored in the generic `xendit_refund_id`.
 */
class ProcessMidtransRefundJob implements ShouldQueue
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

        if (! $reservation || ! $reservation->reservation_number) {
            return;
        }

        if ($reservation->xendit_refund_id) {
            Log::info('Midtrans refund skipped (already refunded)', [
                'reservation_id' => $reservation->id,
                'refund_id' => $reservation->xendit_refund_id,
            ]);

            return;
        }

        $service = $this->resolveService($reservation);
        if (! $service) {
            return;
        }

        try {
            $refundId = $service->refund($reservation->reservation_number, $this->amount, $this->reason);

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
                    'channel' => $reservation->payment_channel,
                    'refund_id' => $refundId,
                ])
                ->log('Refund initiated via Midtrans');
        } catch (\Throwable $e) {
            // 4xx = the refund cannot be done online (channel/balance/state) —
            // retrying will only fail again. 5xx + network errors stay retryable.
            $status = $e instanceof PaymentProviderException ? $e->httpStatus : 0;
            $isUnrecoverable = $status >= 400 && $status < 500;

            Log::error('Midtrans refund failed', [
                'reservation_id' => $reservation->id,
                'channel' => $reservation->payment_channel,
                'error' => $e->getMessage(),
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
                    'unrecoverable' => $isUnrecoverable,
                ])
                ->log($isUnrecoverable
                    ? 'Midtrans refund cannot be processed automatically — manual refund required'
                    : 'Midtrans refund failed (will retry)');

            if ($isUnrecoverable) {
                // Reservation stays cancelled with refund_amount set + xendit_refund_id
                // null, signalling an outstanding manual refund for staff.
                return;
            }

            throw $e;
        }
    }

    private function resolveService(Reservation $reservation): ?MidtransService
    {
        if ($reservation->payment_gateway_id) {
            $gateway = ProjectPaymentGateway::query()->find($reservation->payment_gateway_id);

            if ($gateway && $gateway->provider === 'midtrans') {
                return MidtransService::forGateway($gateway);
            }
        }

        $project = $reservation->event?->project;
        $gateway = $project?->defaultPaymentGateway('midtrans', 'test')
            ?? $project?->defaultPaymentGateway('midtrans', 'live');

        if (! $gateway) {
            Log::error('Midtrans refund: no midtrans gateway found for reservation', [
                'reservation_id' => $reservation->id,
            ]);

            return null;
        }

        return MidtransService::forGateway($gateway);
    }
}
