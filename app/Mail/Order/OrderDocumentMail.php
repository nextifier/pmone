<?php

namespace App\Mail\Order;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Sends an order document (invoice or receipt) to the exhibitor with the
 * uploaded file attached. Recipients are set by the caller via Mail::to().
 */
class OrderDocumentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  'invoice'|'receipt'  $type
     */
    public function __construct(
        public Order $order,
        public string $type,
    ) {}

    public function envelope(): Envelope
    {
        $event = $this->order->brandEvent?->event;
        $label = $this->type === 'invoice' ? 'Invoice' : 'Payment Receipt';
        $subject = "{$label} for Order {$this->order->order_number}".($event ? " - {$event->title}" : '');

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: "emails.order.{$this->type}",
            with: [
                'order' => $this->order,
                'brand' => $this->order->brandEvent?->brand,
                'event' => $this->order->brandEvent?->event,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $media = $this->order->getFirstMedia($this->type);

        if (! $media) {
            return [];
        }

        // Read via the configured disk so attachments work on both local and
        // cloud disks (e.g. Cloudflare R2), mirroring HotelVoucherMail.
        $downloadName = $media->name.($media->extension ? '.'.$media->extension : '');

        return [
            Attachment::fromData(
                fn () => Storage::disk($media->disk)->get($media->getPathRelativeToRoot()),
                $downloadName,
            )->withMime($media->mime_type),
        ];
    }
}
