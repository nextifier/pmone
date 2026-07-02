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
        public ?string $receiptUrl = null,
        public ?string $voucherUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $project = $this->reservation->event?->project;
        $subject = $project
            ? $project->renderEmailSubject('guest_voucher', $this->reservation)
            : "Hotel Voucher: {$this->reservation->reservation_number}";

        return new Envelope(
            to: $this->reservation->guest_email,
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reservation.hotel-voucher',
            with: [
                'reservation' => $this->reservation,
                'receiptUrl' => $this->receiptUrl,
                'voucherUrl' => $this->voucherUrl,
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
        $downloadName = $media->name.($media->extension ? '.'.$media->extension : '');

        return [
            Attachment::fromData(
                fn () => Storage::disk($media->disk)->get($media->getPathRelativeToRoot()),
                $downloadName,
            )->withMime($media->mime_type),
        ];
    }
}
