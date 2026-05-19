<?php

namespace App\Jobs\Reservation;

use App\Mail\Reservation\BookingReceivedMail;
use App\Models\Reservation;
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

    public function __construct(
        public int $reservationId,
        public ?string $rawMagicLinkToken = null,
    ) {}

    public function handle(): void
    {
        $reservation = Reservation::query()
            ->with(['hotel', 'event', 'items.roomType', 'transfers'])
            ->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $appUrl = rtrim(config('app.url'), '/');

        $magicLinkUrl = $this->rawMagicLinkToken
            ? "{$frontendUrl}/hotels/reservation/{$this->rawMagicLinkToken}"
            : "{$frontendUrl}/hotels/success?ref=".$reservation->reservation_number;

        $invoiceUrl = $this->rawMagicLinkToken
            ? "{$appUrl}/api/public/reservations/magic/{$this->rawMagicLinkToken}/invoice.pdf"
            : null;

        Mail::send(new BookingReceivedMail($reservation, $magicLinkUrl, $invoiceUrl));
    }
}
