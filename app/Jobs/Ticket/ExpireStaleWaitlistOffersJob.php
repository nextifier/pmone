<?php

namespace App\Jobs\Ticket;

use App\Services\Ticket\WaitlistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Safety net for waitlist claim offers whose window lapsed unclaimed:
 * releases each held seat and re-offers it to the next FIFO entry. Scheduled
 * (see routes/console.php) - mirrors ExpireUnpaidTicketOrdersJob.
 */
class ExpireStaleWaitlistOffersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(WaitlistService $waitlist): int
    {
        return $waitlist->expireStaleOffers();
    }
}
