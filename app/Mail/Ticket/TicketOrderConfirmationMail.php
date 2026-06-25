<?php

namespace App\Mail\Ticket;

use App\Models\Attendee;
use App\Models\TicketOrder;
use App\Support\AttendeeQrImage;
use App\Support\EventIcs;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketOrderConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public TicketOrder $order,
        public string $magicLinkUrl,
        public ?string $brandLogoUrl = null,
        public ?string $receiptUrl = null,
        public ?string $invoiceUrl = null,
        public ?Attendee $buyerAttendee = null,
        public ?string $buyerEticketUrl = null,
    ) {}

    /**
     * Build the mailable for an order, resolving the magic link, brand logo and
     * (paid-only) receipt/invoice URLs. When the buyer is also attending, the
     * matching attendee is resolved so their personal QR renders inline - the
     * buyer then needs only this one email. Shared by the send job and admin preview.
     */
    public static function for(TicketOrder $order): self
    {
        $rawToken = TicketOrder::magicLinkTokenFor($order->order_number);
        $base = $order->event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');

        $buyerEmail = strtolower(trim((string) $order->buyer_email));
        $buyerAttendee = $buyerEmail === ''
            ? null
            : $order->loadMissing('attendees')->attendees
                ->first(fn (Attendee $a): bool => strtolower(trim((string) $a->email)) === $buyerEmail);

        return new self(
            $order,
            "{$base}/tickets/order/{$rawToken}",
            $order->event?->project?->emailLogoUrl(),
            $order->isFree() ? null : route('public.ticket-orders.receipt-pdf', $rawToken),
            $order->isFree() ? null : route('public.ticket-orders.invoice-pdf', $rawToken),
            $buyerAttendee,
            $buyerAttendee ? "{$base}/tickets/{$buyerAttendee->ulid}" : null,
        );
    }

    public function envelope(): Envelope
    {
        $project = $this->order->event?->project;
        $eventTitle = $this->order->event?->title ?? 'your event';

        return new Envelope(
            from: new Address(config('mail.from.address'), $project?->name ?: config('mail.from.name')),
            replyTo: $project?->email ? [new Address($project->email, (string) ($project->name ?? ''))] : [],
            subject: "Your tickets for {$eventTitle} - {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.order-confirmation',
            text: 'emails.tickets.order-confirmation-text',
            with: [
                'order' => $this->order,
                'event' => $this->order->event,
                'magicLinkUrl' => $this->magicLinkUrl,
                'brandLogoUrl' => $this->brandLogoUrl,
                'receiptUrl' => $this->receiptUrl,
                'invoiceUrl' => $this->invoiceUrl,
                'buyerAttendee' => $this->buyerAttendee,
                'buyerEticketUrl' => $this->buyerEticketUrl,
                // Inline (CID) PNG so the buyer's QR renders without tapping
                // "load images"; falls back to the remote qrImageUrl when absent.
                'qrPng' => $this->buyerAttendee?->qr_token ? AttendeeQrImage::png($this->buyerAttendee->qr_token) : null,
                'qrImageUrl' => $this->buyerAttendee ? route('public.attendees.qr-image', $this->buyerAttendee->ulid) : null,
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->order->event) {
            return [];
        }

        $ics = EventIcs::forEvent($this->order->event, $this->magicLinkUrl);

        if (! $ics) {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $ics, 'event.ics')->withMime('text/calendar'),
        ];
    }
}
