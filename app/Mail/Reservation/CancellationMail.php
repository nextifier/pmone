<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CancellationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public float $refundAmount,
        public ?string $invoiceUrl = null,
        public ?string $receiptUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->reservation->guest_email,
            subject: 'Booking Cancelled - '.$this->reservation->reservation_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reservation.cancellation',
            with: [
                'reservation' => $this->reservation,
                'refundAmount' => $this->refundAmount,
                'invoiceUrl' => $this->invoiceUrl,
                'receiptUrl' => $this->receiptUrl,
            ],
        );
    }
}
