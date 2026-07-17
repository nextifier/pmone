<?php

namespace App\Jobs\Ticket;

use App\Models\TicketOrder;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Issues the attendees for an admin "Bulk Generate" batch. The order + item are
 * created synchronously; this job streams the (possibly thousands of) attendees
 * in the background so a progress poll can watch the count grow.
 */
class GenerateBulkAttendeesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Consumed by supervisor-bulk, which reads the `bulk` queue over the
     * redis-long connection so a run this long is never re-reserved mid-flight.
     * Previously this sat on the default queue, where the supervisor killed it
     * at 600s and Redis re-reserved it every 90s — long enough batches could
     * issue their attendees twice.
     *
     * Only the queue is pinned, not the connection: re-reservation is governed
     * by the connection the *worker* pulls with, and pinning the connection here
     * would push the job at Redis even where the app runs the sync driver.
     */
    public int $timeout = 1800;

    /**
     * @param  array<string, mixed>  $spec
     */
    public function __construct(public int $ticketOrderId, public array $spec)
    {
        $this->onQueue('bulk');
    }

    public function handle(TicketPurchaseService $purchases): void
    {
        $order = TicketOrder::find($this->ticketOrderId);

        if (! $order) {
            return;
        }

        $purchases->generateAttendeesForBatch($order, $this->spec);
    }

    public function failed(\Throwable $e): void
    {
        TicketOrder::whereKey($this->ticketOrderId)->update(['batch_status' => 'failed']);
    }
}
