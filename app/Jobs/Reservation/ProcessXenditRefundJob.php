<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
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

        try {
            $refundId = $client->refundInvoice($reservation->xendit_invoice_id, $this->amount, $this->reason);

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
                    'xendit_refund_id' => $refundId,
                ])
                ->log('Refund initiated via Xendit');
        } catch (\Throwable $e) {
            Log::error('Xendit refund failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            activity()
                ->performedOn($reservation)
                ->event('refund_failed')
                ->withProperties([
                    'project_id' => $reservation->event?->project_id,
                    'reservation_id' => $reservation->id,
                    'refund_amount' => $this->amount,
                    'refund_reason' => $this->reason,
                    'error' => $e->getMessage(),
                ])
                ->log('Xendit refund failed');

            throw $e;
        }
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
