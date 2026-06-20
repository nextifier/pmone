<?php

namespace App\Mail\Ticket;

use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
    ) {}

    public function envelope(): Envelope
    {
        $eventTitle = $this->order->event?->title ?? 'your event';

        return new Envelope(
            subject: "Your tickets for {$eventTitle} - {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.order-confirmation',
            with: [
                'order' => $this->order,
                'magicLinkUrl' => $this->magicLinkUrl,
            ],
        );
    }
}
