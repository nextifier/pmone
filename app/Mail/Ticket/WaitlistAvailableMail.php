<?php

namespace App\Mail\Ticket;

use App\Models\TicketWaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * `notify_only` waitlist mode: a nudge that seats are available again, with
 * no held claim - first to buy wins.
 */
class WaitlistAvailableMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public TicketWaitlistEntry $entry,
        public string $ticketsUrl,
    ) {}

    public function envelope(): Envelope
    {
        $eventTitle = $this->entry->event?->title ?? 'the event';

        return new Envelope(
            subject: "Tickets are available again - {$eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.waitlist-available',
            with: [
                'entry' => $this->entry,
                'event' => $this->entry->event,
                'ticket' => $this->entry->ticket,
                'ticketsUrl' => $this->ticketsUrl,
            ],
        );
    }
}
