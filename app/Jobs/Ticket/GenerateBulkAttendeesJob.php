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

    public int $timeout = 1800;

    /**
     * @param  array<string, mixed>  $spec
     */
    public function __construct(public int $ticketOrderId, public array $spec) {}

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
