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

        $rawToken = $reservations->magicLinkTokenFor($reservation);

        $appUrl = rtrim(config('app.url'), '/');
        // Only a receipt is relevant on a cancellation: it is proof of the
        // payment now being refunded. Gate on whether payment ever happened so
        // a never-paid cancelled booking gets no (meaningless) document link.
        $receiptUrl = $reservation->paid_at !== null
            ? "{$appUrl}/api/public/reservations/magic/{$rawToken}/receipt.pdf"
            : null;

        Mail::send(new CancellationMail($reservation, $this->refundAmount, $receiptUrl));

        // Notify project staff that a booking is cancelled (recipients are
        // configured per project in Website Settings).
        SendStaffReservationNotificationJob::dispatch($reservation->id, 'cancelled');
    }
}
