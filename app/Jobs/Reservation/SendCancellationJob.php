<?php

namespace App\Jobs\Reservation;

use App\Mail\Reservation\CancellationMail;
use App\Models\Reservation;
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

    public function handle(): void
    {
        $reservation = Reservation::query()->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        Mail::send(new CancellationMail($reservation, $this->refundAmount));
    }
}
