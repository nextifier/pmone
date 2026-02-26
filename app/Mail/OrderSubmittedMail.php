<?php

namespace App\Mail;

use App\Models\Brand;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Event $event,
        public Brand $brand
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order #{$this->order->order_number} — {$this->brand->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-submitted',
            with: [
                'order' => $this->order,
                'event' => $this->event,
                'brand' => $this->brand,
                'items' => $this->order->items,
            ],
        );
    }
}
