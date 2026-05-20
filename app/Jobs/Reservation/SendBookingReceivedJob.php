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

        // Roll a fresh magic-link token so the email's document links keep
        // working long-term, mirroring SendHotelVoucherJob / SendCancellationJob.
        // This job only ever runs once the reservation is already paid, so the
        // success page (which resolves by reservation number) is unaffected.
        [$rawToken, $hashedToken] = $reservations->generateMagicLinkToken();
        $reservation->update([
            'magic_link_token' => $hashedToken,
            'magic_link_expires_at' => now()->addYear(),
        ]);

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $appUrl = rtrim(config('app.url'), '/');

        $magicLinkUrl = "{$frontendUrl}/hotels/reservation/{$rawToken}";
        $invoiceUrl = "{$appUrl}/api/public/reservations/magic/{$rawToken}/invoice.pdf";
        $receiptUrl = $reservation->status->isPaid()
            ? "{$appUrl}/api/public/reservations/magic/{$rawToken}/receipt.pdf"
            : null;

        Mail::send(new BookingReceivedMail($reservation, $magicLinkUrl, $invoiceUrl, $receiptUrl));
    }
}
