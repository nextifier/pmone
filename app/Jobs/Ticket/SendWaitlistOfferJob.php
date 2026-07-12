<?php

namespace App\Jobs\Ticket;

use App\Mail\Ticket\WaitlistOfferMail;
use App\Models\TicketWaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the time-limited claim link for a waitlist entry whose seat was just
 * held (WaitlistService::offerReleasedSeats(), auto_offer mode only).
 */
class SendWaitlistOfferJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $waitlistEntryId)
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
        Log::error('Failed to send waitlist offer email', [
            'waitlist_entry_id' => $this->waitlistEntryId,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(): void
    {
        $entry = TicketWaitlistEntry::query()->with(['event', 'ticket'])->find($this->waitlistEntryId);

        if (! $entry || $entry->email === '' || $entry->claim_token === null) {
            return;
        }

        $base = $entry->event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');
        $claimUrl = "{$base}/tickets/waitlist/claim/{$entry->claim_token}";

        Mail::to($entry->email)->send(new WaitlistOfferMail($entry, $claimUrl));
    }
}
