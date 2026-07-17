<?php

namespace App\Jobs\Ticket;

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\TicketOrder;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Safety net for unpaid ticket orders whose payment window lapsed. Normally the
 * Xendit `invoice.expired` webhook expires them; this scheduled job reconciles
 * any that the webhook missed, releasing the held stock + session capacity.
 */
class ExpireUnpaidTicketOrdersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('tickets');
    }

    public function handle(TicketPurchaseService $purchases): int
    {
        $expiring = TicketOrder::query()
            ->where('status', TicketOrderStatus::PendingPayment->value)
            ->where('payment_expires_at', '<=', now())
            ->with(['items', 'adjustments.promotionRule'])
            ->get();

        foreach ($expiring as $order) {
            $purchases->expireOrder($order);
        }

        return $expiring->count();
    }
}
