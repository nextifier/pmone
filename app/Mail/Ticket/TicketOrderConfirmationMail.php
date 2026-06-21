<?php

namespace App\Mail\Ticket;

use App\Models\TicketOrder;
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
    ) {}

    /**
     * Build the mailable for an order, resolving the magic link, brand logo and
     * (paid-only) receipt/invoice URLs. Shared by the send job and admin preview.
     */
    public static function for(TicketOrder $order): self
    {
        $rawToken = TicketOrder::magicLinkTokenFor($order->order_number);
        $base = $order->event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');

        return new self(
            $order,
            "{$base}/tickets/order/{$rawToken}",
            $order->event?->project?->emailLogoUrl(),
            $order->isFree() ? null : route('public.ticket-orders.receipt-pdf', $rawToken),
            $order->isFree() ? null : route('public.ticket-orders.invoice-pdf', $rawToken),
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
