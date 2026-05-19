<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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

        // Read the file via the configured disk so attachments work on both
        // local and cloud disks (e.g. Cloudflare R2). `$media->getPath()`
        // returns a non-resolvable path on remote disks, which previously
        // caused the mail transport to send an empty attachment body.
        return [
            Attachment::fromData(
                fn () => Storage::disk($media->disk)->get($media->getPathRelativeToRoot()),
                $media->file_name,
            )->withMime($media->mime_type),
        ];
    }
}
