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
        public ?string $invoiceUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $isPaid = in_array($this->reservation->status?->value, ['paid', 'voucher_sent'], true);
        $subject = $isPaid
            ? 'Payment Confirmed - '.$this->reservation->reservation_number
            : 'Booking Received - '.$this->reservation->reservation_number;

        return new Envelope(
            to: $this->reservation->guest_email,
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reservation.booking-received',
            with: [
                'reservation' => $this->reservation,
                'magicLinkUrl' => $this->magicLinkUrl,
                'invoiceUrl' => $this->invoiceUrl,
            ],
        );
    }
}
