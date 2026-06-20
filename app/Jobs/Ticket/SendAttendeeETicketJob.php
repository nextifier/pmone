<?php

namespace App\Jobs\Ticket;

use App\Mail\Ticket\AttendeeETicketMail;
use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a single attendee their own e-ticket link (used by Bulk Generate's
 * auto-email delivery). Unlike SendTicketOrderConfirmationJob it targets the
 * attendee, not the order buyer.
 */
class SendAttendeeETicketJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $attendeeId) {}

    public function handle(): void
    {
        $attendee = Attendee::query()
            ->with(['ticket', 'ticketOrderItem.ticketOrder.event.project.links'])
            ->find($this->attendeeId);

        if (! $attendee || ! $attendee->email) {
            return;
        }

        $event = $attendee->ticketOrderItem?->ticketOrder?->event;

        // The shareable e-ticket page is served by the event's public website
        // (pmone-events), not the admin app — so prefer the event's Website URL,
        // falling back to the configured frontend URL.
        $base = $event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');
        $eticketUrl = "{$base}/tickets/{$attendee->ulid}";

        // Absolute API URL to the on-the-fly QR image (no file stored, no
        // attachment) so the email can embed it as a plain <img src>.
        $qrImageUrl = route('public.attendees.qr-image', $attendee->ulid);

        // One-click dashboard sign-in button - email ONLY (never on the shareable
        // e-ticket page). The button carries a secret HMAC token; opening it lands
        // on the e-ticket page with ?login=<token>, which mints a fresh magic link.
        // Shown only when the event allows it AND the attendee's email maps to a
        // loginable visitor account (works for returning account holders too).
        $loginEnabled = (bool) ($event?->settings['tickets']['login_button_enabled'] ?? true);
        $dashboardUrl = ($loginEnabled && $attendee->resolveLoginableUser())
            ? "{$eticketUrl}?login=".$attendee->dashboardLoginToken()
            : null;

        Mail::to($attendee->email)->send(
            new AttendeeETicketMail($attendee, $eticketUrl, $event, $dashboardUrl, $qrImageUrl)
        );
    }
}
