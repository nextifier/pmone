<?php

namespace App\Services\Ticket;

use App\Enums\Ticketing\TicketWaitlistEntryStatus;
use App\Enums\Ticketing\WaitlistMode;
use App\Jobs\Ticket\SendWaitlistAvailableNotifyJob;
use App\Jobs\Ticket\SendWaitlistJoinedJob;
use App\Jobs\Ticket\SendWaitlistOfferJob;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketWaitlistEntry;
use Illuminate\Support\Facades\DB;

/**
 * Waitlist for sold-out tickets (Plan 020): join the FIFO queue, offer a
 * released seat to the next entries with a time-limited claim, resolve a
 * claim into a real order, and sweep offers whose window lapsed.
 *
 * Correctness note (the whole point of this class): `offerReleasedSeats()`
 * genuinely HOLDS the seat via Plan 016's atomic `Ticket::reserve()` BEFORE
 * emailing the offer, and `expireStaleOffers()` releases it via
 * `Ticket::release()` before re-offering - so the seat is never double-
 * promised to two different claimers. See `claim()` and
 * `TicketPurchaseService::createOrderFromWaitlistClaim()` for the matching
 * "consume the existing hold, don't reserve again" half of that contract.
 */
class WaitlistService
{
    /**
     * How long a claim offer stays valid before it is swept back to the pool
     * by expireStaleOffers() and re-offered to the next FIFO entry.
     */
    protected const OFFER_WINDOW_MINUTES = 60;

    public function __construct(protected TicketPurchaseService $purchases) {}

    /**
     * Add a buyer to a sold-out ticket's waitlist. Idempotent by (ticket_id,
     * email): re-submitting while already `waiting`/`offered` returns the
     * existing entry unchanged; re-joining after a terminal state
     * (claimed/expired/cancelled) re-activates it at the back of the queue
     * instead of inserting a second row (the unique index forbids that).
     *
     * @param  array<string, mixed>  $data
     */
    public function join(array $data): TicketWaitlistEntry
    {
        $ticket = Ticket::query()
            ->where('id', $data['ticket_id'])
            ->where('event_id', $data['event_id'])
            ->firstOrFail();

        $email = strtolower(trim((string) $data['email']));

        return DB::transaction(function () use ($data, $ticket, $email) {
            $existing = TicketWaitlistEntry::query()
                ->where('ticket_id', $ticket->id)
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, [TicketWaitlistEntryStatus::Waiting, TicketWaitlistEntryStatus::Offered], true)) {
                return $existing;
            }

            $position = (int) (TicketWaitlistEntry::query()->where('ticket_id', $ticket->id)->max('position') ?? 0) + 1;

            $attributes = [
                'event_id' => $ticket->event_id,
                'ticket_id' => $ticket->id,
                'email' => $email,
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
                'status' => TicketWaitlistEntryStatus::Waiting,
                'position' => $position,
                'offered_at' => null,
                'offer_expires_at' => null,
                'claim_token' => null,
            ];

            $entry = $existing
                ? tap($existing)->update($attributes)
                : TicketWaitlistEntry::create($attributes);

            SendWaitlistJoinedJob::dispatch($entry->id)->afterCommit();

            return $entry->fresh();
        });
    }

    /**
     * Offer up to `$qty` just-released units of `$ticket` to the next FIFO
     * waiting entries whose requested quantity fits within it. In
     * `auto_offer` mode (default) each offered entry's seat is atomically
     * reserved via `Ticket::reserve()` BEFORE it is marked `offered` and
     * emailed - so the claim window genuinely holds the seat instead of
     * merely promising it. In `notify_only` mode nothing is reserved; the
     * entry stays `waiting` and just gets an "available again" nudge
     * (first-to-buy-wins, per the event's chosen trade-off).
     *
     * Each candidate is locked + re-checked inside its own small transaction
     * so a second concurrent release (or the expiry sweep) can never offer
     * the same entry twice.
     *
     * @return int number of entries offered/notified
     */
    public function offerReleasedSeats(Ticket $ticket, int $qty): int
    {
        if ($qty < 1) {
            return 0;
        }

        $mode = $ticket->event?->waitlist_mode ?? WaitlistMode::AutoOffer;

        $remaining = $qty;
        $processed = 0;

        // Capped candidate scan (not just the first $qty rows): entries
        // whose own quantity doesn't fit the remaining budget are skipped
        // in place so a smaller entry further down the FIFO line still gets
        // a chance at the seats that are left.
        $candidates = TicketWaitlistEntry::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', TicketWaitlistEntryStatus::Waiting->value)
            ->orderBy('position')
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($candidates as $candidate) {
            if ($remaining <= 0) {
                break;
            }

            if ($candidate->quantity > $remaining) {
                continue;
            }

            $handled = DB::transaction(function () use ($candidate, $ticket, $mode) {
                $entry = TicketWaitlistEntry::query()->whereKey($candidate->id)->lockForUpdate()->first();

                if (! $entry || $entry->status !== TicketWaitlistEntryStatus::Waiting) {
                    return false;
                }

                if ($mode === WaitlistMode::NotifyOnly) {
                    SendWaitlistAvailableNotifyJob::dispatch($entry->id)->afterCommit();

                    return true;
                }

                // The atomic conditional UPDATE from Plan 016 - this is the
                // hold. If it fails, the seat is genuinely gone (e.g. a
                // concurrent admin comp batch), so the entry stays `waiting`
                // for the next release instead of being falsely offered.
                if (! $ticket->reserve($entry->quantity)) {
                    return false;
                }

                $entry->forceFill([
                    'status' => TicketWaitlistEntryStatus::Offered,
                    'offered_at' => now(),
                    'offer_expires_at' => now()->addMinutes(self::OFFER_WINDOW_MINUTES),
                    'claim_token' => TicketWaitlistEntry::generateClaimToken(),
                ])->save();

                SendWaitlistOfferJob::dispatch($entry->id)->afterCommit();

                return true;
            });

            if ($handled) {
                $remaining -= $candidate->quantity;
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Resolve a claim token into a real order for the already-held quantity.
     * The seat's `Ticket::reserve()` call already happened in
     * offerReleasedSeats() - this method (via
     * TicketPurchaseService::createOrderFromWaitlistClaim()) does NOT
     * reserve again, only the order/attendees are built for the held seat.
     *
     * The entry row is locked for the whole operation so a concurrent
     * expireStaleOffers() sweep racing the same offer's expiry can't release
     * the seat out from under an in-flight claim (and vice versa): whichever
     * transaction commits first wins, the other re-reads the row and finds
     * the state already moved on.
     */
    public function claim(string $token): TicketOrder
    {
        return DB::transaction(function () use ($token) {
            $entry = TicketWaitlistEntry::query()->where('claim_token', $token)->lockForUpdate()->first();

            abort_if(! $entry, 404, 'This claim link is invalid.');
            abort_unless($entry->status === TicketWaitlistEntryStatus::Offered, 410, 'This offer is no longer available.');
            abort_if($entry->offer_expires_at === null || $entry->offer_expires_at->isPast(), 410, 'This offer has expired.');

            $entry->forceFill(['status' => TicketWaitlistEntryStatus::Claimed])->save();

            return $this->purchases->createOrderFromWaitlistClaim($entry);
        });
    }

    /**
     * Sweep offers whose claim window lapsed unclaimed: release the held
     * seat back via `Ticket::release()` (Plan 016's guarded decrement), mark
     * the entry `expired`, then re-offer the freed quantity to the next FIFO
     * waiting entry. Meant to run on a schedule (see routes/console.php).
     *
     * @return int number of offers expired
     */
    public function expireStaleOffers(): int
    {
        $stale = TicketWaitlistEntry::query()
            ->where('status', TicketWaitlistEntryStatus::Offered->value)
            ->where('offer_expires_at', '<=', now())
            ->get();

        $expired = 0;

        foreach ($stale as $candidate) {
            $ticket = DB::transaction(function () use ($candidate) {
                $entry = TicketWaitlistEntry::query()->whereKey($candidate->id)->lockForUpdate()->first();

                if (! $entry || $entry->status !== TicketWaitlistEntryStatus::Offered) {
                    return null;
                }
                if ($entry->offer_expires_at === null || $entry->offer_expires_at->isFuture()) {
                    return null;
                }

                $ticket = Ticket::query()->find($entry->ticket_id);
                $ticket?->release($entry->quantity);

                $entry->forceFill(['status' => TicketWaitlistEntryStatus::Expired])->save();

                return $ticket;
            });

            if ($ticket) {
                $expired++;
                // Re-offer outside the entry's own transaction (already
                // committed above) so offerReleasedSeats() sees the freed
                // sold_count when it re-runs Ticket::reserve() for the next
                // FIFO entry.
                $this->offerReleasedSeats($ticket, $candidate->quantity);
            }
        }

        return $expired;
    }
}
