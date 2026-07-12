<?php

namespace App\Jobs\Ticket;

use App\Mail\Ticket\AttendeeETicketMail;
use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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

    public int $tries = 3;

    public function __construct(public int $attendeeId, public bool $consolidated = false)
    {
        $this->onQueue('tickets');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Failed to send attendee e-ticket email', [
            'attendee_id' => $this->attendeeId,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(): void
    {
        $attendee = Attendee::query()
            ->with([
                'ticket',
                'ticketOrderItem.ticketOrder.event.project.media',
                'ticketOrderItem.ticketOrder.event.project.links',
                'ticketOrderItem.selectedEventDay',
                'ticketOrderItem.ticketSession',
            ])
            ->find($this->attendeeId);

        if (! $attendee || ! $attendee->email) {
            return;
        }

        Mail::to($attendee->email)->send(AttendeeETicketMail::for($attendee, $this->consolidated));
    }
}
