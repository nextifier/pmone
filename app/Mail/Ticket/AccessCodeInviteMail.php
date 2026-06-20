<?php

namespace App\Mail\Ticket;

use App\Models\AccessCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessCodeInviteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AccessCode $code,
        public string $inviteUrl,
    ) {}

    public function envelope(): Envelope
    {
        $eventTitle = $this->code->event?->title ?? 'an event';

        return new Envelope(
            subject: "You're invited: {$eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.access-code-invite',
            with: [
                'code' => $this->code,
                'inviteUrl' => $this->inviteUrl,
                'event' => $this->code->event,
                'unlocks' => $this->code->relationLoaded('unlocks') ? $this->code->unlocks : collect(),
            ],
        );
    }
}
