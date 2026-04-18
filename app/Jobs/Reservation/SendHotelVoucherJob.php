<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
use App\Mail\Reservation\HotelVoucherMail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendHotelVoucherJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $reservationId) {}

    public function handle(): void
    {
        $reservation = Reservation::query()
            ->with(['hotel', 'items.roomType', 'media'])
            ->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        if (! $reservation->hasMedia('voucher')) {
            return;
        }

        Mail::send(new HotelVoucherMail($reservation));

        $reservation->update([
            'status' => ReservationStatus::VoucherSent,
            'voucher_sent_at' => now(),
        ]);
    }
}
