<?php

namespace App\Jobs\Ticket;

use App\Models\TicketOrder;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Opens the payment-gateway checkout for a pending paid ticket order in the
 * background, so the buyer's checkout request (and the PHP-FPM worker
 * handling it) never blocks on the gateway's ~300-800ms HTTP round-trip
 * (Plan 017 - pairs with Plan 016's atomic inventory counters). The result
 * page polls GET /api/tickets/orders/{ulid} and shows "preparing payment"
 * until payment_url appears.
 *
 * Idempotent via TicketPurchaseService::openTicketCheckout(): a no-op once
 * the order already has a payment_url or is no longer PendingPayment (a
 * webhook confirmed it, or it expired, before this job ran).
 *
 * On gateway failure, logs (via XenditErrorMapper, inside
 * TicketPurchaseService::logCheckoutFailure()) and releases itself back onto
 * the queue for a delayed retry rather than throwing - a thrown exception
 * here would, under the `sync` queue driver used in tests/console, propagate
 * synchronously all the way back into the buyer's createOrder() call. The
 * order stays PendingPayment throughout, so ExpireUnpaidTicketOrdersJob
 * still reclaims it if the gateway never recovers within the retry budget.
 */
class CreateTicketCheckoutJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $ticketOrderId)
    {
        $this->onQueue('tickets');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(TicketPurchaseService $purchases): void
    {
        $order = TicketOrder::query()->with('event.project')->find($this->ticketOrderId);

        if (! $order) {
            return;
        }

        try {
            $purchases->openTicketCheckout($order);
        } catch (\Throwable $e) {
            $purchases->logCheckoutFailure($order, $e);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Ticket checkout job exhausted retries - order kept pending for the expiry job to reclaim', [
            'ticket_order_id' => $this->ticketOrderId,
            'error' => $e->getMessage(),
        ]);
    }
}
