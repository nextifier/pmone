<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReceivedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public string $magicLinkUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->reservation->guest_email,
            subject: 'Booking Confirmed - '.$this->reservation->reservation_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reservation.booking-received',
            with: [
                'reservation' => $this->reservation,
                'magicLinkUrl' => $this->magicLinkUrl,
            ],
        );
    }
}
