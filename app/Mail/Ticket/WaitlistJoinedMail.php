<?php

namespace App\Mail\Ticket;

use App\Models\TicketWaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistJoinedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public TicketWaitlistEntry $entry) {}

    public function envelope(): Envelope
    {
        $eventTitle = $this->entry->event?->title ?? 'the event';

        return new Envelope(
            subject: "You're on the waitlist - {$eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.waitlist-joined',
            with: [
                'entry' => $this->entry,
                'event' => $this->entry->event,
                'ticket' => $this->entry->ticket,
            ],
        );
    }
}
