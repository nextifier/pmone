<?php

namespace App\Jobs\Reservation;

use App\Mail\Reservation\BookingReceivedMail;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBookingReceivedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $reservationId) {}

    public function handle(ReservationService $reservations): void
    {
        $reservation = Reservation::query()
            ->with(['hotel', 'event', 'items.roomType', 'transfers'])
            ->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        // Reuse the reservation's stable magic-link token so the email links
        // match the token already embedded in the Xendit success_url.
        $rawToken = $reservations->magicLinkTokenFor($reservation);

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $appUrl = rtrim(config('app.url'), '/');

        $magicLinkUrl = "{$frontendUrl}/hotels/reservation/{$rawToken}";
        // The reservation is already paid by the time this email is sent, so a
        // receipt (proof of payment) is the relevant document - not an invoice.
        $receiptUrl = $reservation->status->isPaid()
            ? "{$appUrl}/api/public/reservations/magic/{$rawToken}/receipt.pdf"
            : null;

        Mail::send(new BookingReceivedMail($reservation, $magicLinkUrl, $receiptUrl));
    }
}
