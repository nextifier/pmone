<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffReservationNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  'confirmed'|'cancelled'  $eventType
     */
    public function __construct(
        public Reservation $reservation,
        public string $eventType,
        public string $emailSubject,
        public ?string $reservationUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        // Reply-To the guest so staff can respond to them in one click.
        return new Envelope(
            subject: $this->emailSubject,
            replyTo: [
                new Address(
                    $this->reservation->guest_email,
                    $this->reservation->guest_name ?? '',
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reservation.staff-notification',
            with: [
                'reservation' => $this->reservation,
                'eventType' => $this->eventType,
                'reservationUrl' => $this->reservationUrl,
            ],
        );
    }
}
