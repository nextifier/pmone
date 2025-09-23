<?php

namespace App\Mail;

use App\Models\MagicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MagicLink $magicLink
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PM One Login Link',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.magic-link',
            with: [
                'loginUrl' => config('app.url').'/auth/magic-link/'.$this->magicLink->token,
                'expiresAt' => $this->magicLink->expires_at,
            ],
        );
    }
}
