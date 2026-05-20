<?php

namespace App\Jobs\Reservation;

use App\Mail\Reservation\CancellationMail;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCancellationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $reservationId,
        public float $refundAmount,
    ) {}

    public function handle(ReservationService $reservations): void
    {
        $reservation = Reservation::query()
            ->with(['hotel', 'event', 'items.roomType'])
            ->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        [$rawToken, $hashedToken] = $reservations->generateMagicLinkToken();
        $reservation->update([
            'magic_link_token' => $hashedToken,
            'magic_link_expires_at' => now()->addYear(),
        ]);

        $appUrl = rtrim(config('app.url'), '/');
        $invoiceUrl = "{$appUrl}/api/public/reservations/magic/{$rawToken}/invoice.pdf";
        // Receipt is proof of the original payment, so it stays available even
        // after cancellation/refund - gate on whether payment ever happened.
        $receiptUrl = $reservation->paid_at !== null
            ? "{$appUrl}/api/public/reservations/magic/{$rawToken}/receipt.pdf"
            : null;

        Mail::send(new CancellationMail($reservation, $this->refundAmount, $invoiceUrl, $receiptUrl));
    }
}
