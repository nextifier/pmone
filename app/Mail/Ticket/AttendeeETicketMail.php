<?php

namespace App\Mail\Ticket;

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendeeETicketMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Attendee $attendee,
        public string $eticketUrl,
        public ?Event $event = null,
        public ?string $dashboardUrl = null,
        public ?string $qrImageUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $eventTitle = $this->event?->title ?? 'your event';

        return new Envelope(
            subject: "Your ticket for {$eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.attendee-eticket',
            with: [
                'attendee' => $this->attendee,
                'eticketUrl' => $this->eticketUrl,
                'event' => $this->event,
                'dashboardUrl' => $this->dashboardUrl,
                'qrImageUrl' => $this->qrImageUrl,
            ],
        );
    }
}
