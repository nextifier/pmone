<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
use App\Mail\Reservation\HotelVoucherMail;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
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

    public function handle(ReservationService $reservations): void
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

        // Reservation may have been cancelled, expired, or refunded between
        // dispatch and execution — never send a voucher for a non-active booking.
        if (! $reservation->status->isPaid()) {
            return;
        }

        $rawToken = $reservations->magicLinkTokenFor($reservation);

        $appUrl = rtrim(config('app.url'), '/');
        $voucherUrl = "{$appUrl}/api/public/reservations/magic/{$rawToken}/voucher";
        $receiptUrl = "{$appUrl}/api/public/reservations/magic/{$rawToken}/receipt.pdf";

        Mail::send(new HotelVoucherMail($reservation, $receiptUrl, $voucherUrl));

        $reservation->update([
            'status' => ReservationStatus::VoucherSent,
            'voucher_sent_at' => now(),
        ]);
    }
}
