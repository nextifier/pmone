<?php

namespace App\Jobs\Ticket;

use App\Mail\Ticket\WaitlistAvailableMail;
use App\Models\TicketWaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * `notify_only` waitlist mode: emails "tickets are available again" WITHOUT
 * holding a seat - first to buy wins. The entry stays `waiting` (no claim
 * token, no reservation), so it can be notified again on a later release.
 */
class SendWaitlistAvailableNotifyJob implements ShouldQueue
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
        Log::error('Failed to send waitlist available-again email', [
            'waitlist_entry_id' => $this->waitlistEntryId,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(): void
    {
        $entry = TicketWaitlistEntry::query()->with(['event', 'ticket'])->find($this->waitlistEntryId);

        if (! $entry || $entry->email === '') {
            return;
        }

        $base = $entry->event?->publicBaseUrl() ?? rtrim((string) config('app.frontend_url'), '/');
        $ticketsUrl = "{$base}/tickets";

        Mail::to($entry->email)->send(new WaitlistAvailableMail($entry, $ticketsUrl));
    }
}
