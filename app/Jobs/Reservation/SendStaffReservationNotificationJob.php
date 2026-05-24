<?php

namespace App\Jobs\Reservation;

use App\Mail\Reservation\StaffReservationNotificationMail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendStaffReservationNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  'confirmed'|'cancelled'  $eventType
     */
    public function __construct(
        public int $reservationId,
        public string $eventType,
    ) {}

    public function handle(): void
    {
        $reservation = Reservation::query()
            ->with(['hotel', 'event.project', 'items.roomType', 'transfers'])
            ->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        $project = $reservation->event?->project;

        if (! $project) {
            return;
        }

        $config = $project->getHotelNotificationEmailConfig();

        // No staff recipients configured for this project — nothing to send.
        if (empty($config['to'])) {
            return;
        }

        $subjectKey = $this->eventType === 'cancelled' ? 'staff_cancelled' : 'staff_confirmed';

        // Link staff to the admin reservation page where they can manage it.
        $reservationUrl = rtrim(config('app.frontend_url'), '/')
            ."/projects/{$project->username}/events/{$reservation->event->slug}/reservations/{$reservation->ulid}";

        $mailable = new StaffReservationNotificationMail(
            reservation: $reservation,
            eventType: $this->eventType,
            emailSubject: $project->renderEmailSubject($subjectKey, $reservation),
            reservationUrl: $reservationUrl,
        );

        $mailable->to($config['to']);

        if (! empty($config['cc'])) {
            $mailable->cc($config['cc']);
        }

        if (! empty($config['bcc'])) {
            $mailable->bcc($config['bcc']);
        }

        Mail::send($mailable);
    }
}
