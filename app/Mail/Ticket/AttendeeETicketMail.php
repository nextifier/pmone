<?php

namespace App\Mail\Ticket;

use App\Models\Attendee;
use App\Models\Event;
use App\Support\AttendeeQrImage;
use App\Support\EventIcs;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
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
        public ?string $brandLogoUrl = null,
        public bool $consolidated = false,
    ) {}

    /**
     * Build the mailable for an attendee, resolving the e-ticket URL, QR endpoint,
     * one-tap dashboard link and brand logo. Shared by the send job and admin
     * preview so both stay in lockstep.
     */
    public static function for(Attendee $attendee, bool $consolidated = false): self
    {
        $event = $attendee->ticketOrderItem?->ticketOrder?->event;
        $base = $event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');
        $eticketUrl = "{$base}/tickets/{$attendee->ulid}";

        $loginEnabled = (bool) ($event?->settings['tickets']['login_button_enabled'] ?? true);
        $dashboardUrl = ($loginEnabled && $attendee->resolveLoginableUser())
            ? "{$eticketUrl}?login=".$attendee->dashboardLoginToken()
            : null;

        return new self(
            $attendee,
            $eticketUrl,
            $event,
            $dashboardUrl,
            route('public.attendees.qr-image', $attendee->ulid),
            $event?->project?->emailLogoUrl(),
            $consolidated,
        );
    }

    public function envelope(): Envelope
    {
        $project = $this->event?->project;
        $eventTitle = $this->event?->title ?? 'your event';
        // A unique-per-ticket subject (attendee + order number) keeps Gmail/Outlook
        // from threading every e-ticket together and trimming the QR as "quoted".
        $order = $this->attendee->ticketOrderItem?->ticketOrder;
        $suffix = collect([$this->attendee->name, $order?->order_number])->filter()->implode(' - ');

        return new Envelope(
            // Keep the DKIM-aligned from address; only the display name + reply-to
            // carry the organizer's identity so replies reach them, not the platform.
            from: new Address(config('mail.from.address'), $project?->name ?: config('mail.from.name')),
            replyTo: $project?->email ? [new Address($project->email, (string) ($project->name ?? ''))] : [],
            subject: trim("Your ticket for {$eventTitle}".($suffix !== '' ? " - {$suffix}" : '')),
        );
    }

    public function content(): Content
    {
        $item = $this->attendee->ticketOrderItem;
        $order = $item?->ticketOrder;

        return new Content(
            view: 'emails.tickets.attendee-eticket',
            text: 'emails.tickets.attendee-eticket-text',
            with: [
                'attendee' => $this->attendee,
                'eticketUrl' => $this->eticketUrl,
                'event' => $this->event,
                'dashboardUrl' => $this->dashboardUrl,
                'qrImageUrl' => $this->qrImageUrl,
                // Inline (CID) PNG so the QR renders without the recipient tapping
                // "load images"; falls back to the remote qrImageUrl when absent.
                'qrPng' => $this->attendee->qr_token ? AttendeeQrImage::png($this->attendee->qr_token) : null,
                'brandLogoUrl' => $this->brandLogoUrl,
                'consolidated' => $this->consolidated,
                'order' => $order,
                'ticketDayLabel' => $this->dayLabel(),
                'ticketSessionLabel' => $this->sessionLabel(),
            ],
        );
    }

    public function attachments(): array
    {
        $ics = EventIcs::forAttendee($this->attendee, $this->eticketUrl);

        if (! $ics) {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $ics, 'event.ics')->withMime('text/calendar'),
        ];
    }

    protected function dayLabel(): ?string
    {
        $day = $this->attendee->ticketOrderItem?->selectedEventDay;

        if (! $day) {
            return null;
        }

        $label = $day->label ?: 'Day '.$day->day_number;

        return $day->date ? $label.' - '.$day->date->format('D, M j, Y') : $label;
    }

    protected function sessionLabel(): ?string
    {
        $session = $this->attendee->ticketOrderItem?->ticketSession;

        if (! $session) {
            return null;
        }

        $parts = array_filter([
            $session->label,
            $session->starts_at
                ? $session->starts_at->format('g:i A').($session->ends_at ? ' - '.$session->ends_at->format('g:i A') : '')
                : null,
            $session->location ? '@ '.$session->location : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }
}
