<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireUnpaidReservationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): int
    {
        return Reservation::query()
            ->where('status', ReservationStatus::PendingPayment->value)
            ->where('payment_expires_at', '<=', now())
            ->update([
                'status' => ReservationStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }
}
