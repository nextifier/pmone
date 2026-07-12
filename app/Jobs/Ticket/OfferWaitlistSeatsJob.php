<?php

namespace App\Jobs\Ticket;

use App\Models\Ticket;
use App\Services\Ticket\WaitlistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Offer a just-released ticket quantity to the next FIFO waitlist entries.
 * Dispatched (afterCommit) by TicketPurchaseService::expireOrder() and
 * ::refundAttendee() right after each release, so WaitlistService sees the
 * freed sold_count before it tries to atomically re-reserve any of it.
 */
class OfferWaitlistSeatsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $ticketId, public int $quantity)
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
        Log::error('Failed to offer released seats to the waitlist', [
            'ticket_id' => $this->ticketId,
            'quantity' => $this->quantity,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(WaitlistService $waitlist): void
    {
        if ($this->quantity < 1) {
            return;
        }

        $ticket = Ticket::find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $waitlist->offerReleasedSeats($ticket, $this->quantity);
    }
}
