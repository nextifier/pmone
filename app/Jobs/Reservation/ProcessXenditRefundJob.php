<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function handle(XenditService $xendit): void
    {
        $reservation = Reservation::query()->find($this->reservationId);

        if (! $reservation || ! $reservation->xendit_invoice_id) {
            return;
        }

        try {
            $refundId = $xendit->refundInvoice($reservation->xendit_invoice_id, $this->amount, $this->reason);

            $reservation->update([
                'status' => ReservationStatus::Refunded,
                'refunded_at' => now(),
                'refund_amount' => $this->amount,
                'refund_reason' => $this->reason,
                'xendit_refund_id' => $refundId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Xendit refund failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
