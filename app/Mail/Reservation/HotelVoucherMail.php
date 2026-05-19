<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HotelVoucherMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public ?string $invoiceUrl = null,
        public ?string $receiptUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->reservation->guest_email,
            subject: 'Hotel Voucher - '.$this->reservation->reservation_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reservation.hotel-voucher',
            with: [
                'reservation' => $this->reservation,
                'invoiceUrl' => $this->invoiceUrl,
                'receiptUrl' => $this->receiptUrl,
            ],
        );
    }

    public function attachments(): array
    {
        $media = $this->reservation->getFirstMedia('voucher');

        if (! $media) {
            return [];
        }

        return [
            Attachment::fromPath($media->getPath())->as($media->file_name),
        ];
    }
}
