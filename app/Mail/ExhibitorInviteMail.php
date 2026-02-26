<?php

namespace App\Mail;

use App\Models\Brand;
use App\Models\Event;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExhibitorInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Brand $brand,
        public Event $event,
        public ?string $plainPassword = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to manage {$this->brand->name} at {$this->event->title}",
        );
    }

    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $loginUrl = $frontendUrl.'/login';

        // Generate magic link for one-click login
        $magicLink = MagicLink::generate($this->user->email);
        $magicLinkUrl = config('app.url').'/auth/magic-link/'.$magicLink->token;

        return new Content(
            markdown: 'emails.exhibitor-invite',
            with: [
                'userName' => $this->user->name,
                'brandName' => $this->brand->name,
                'eventTitle' => $this->event->title,
                'email' => $this->user->email,
                'password' => $this->plainPassword,
                'loginUrl' => $loginUrl,
                'magicLinkUrl' => $magicLinkUrl,
            ],
        );
    }
}
