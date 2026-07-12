<?php

use App\Contracts\Payment\CreatesCheckout;
use App\Enums\Ticketing\TicketWaitlistEntryStatus;
use App\Enums\Ticketing\WaitlistMode;
use App\Jobs\Ticket\OfferWaitlistSeatsJob;
use App\Jobs\Ticket\SendWaitlistAvailableNotifyJob;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\TicketWaitlistEntry;
use App\Services\Ticket\TicketPurchaseService;
use App\Services\Ticket\WaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->purchases = app(TicketPurchaseService::class);
    $this->waitlist = app(WaitlistService::class);
});

/**
 * A ticket with a fixed stock, on sale now - mirrors
 * PurchaseInventoryIntegrityTest's priceableTicket(), named distinctly since
 * Pest requires globally-unique top-level helper function names.
 */
function waitlistTicket(Event $event, int $stock, float $price = 0): Ticket
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id, 'stock' => $stock, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

/**
 * Buy out the whole stock of a FREE ticket - the order confirms immediately
 * (no payment step), which is all a refund scenario needs.
 */
function sellOutFree(TicketPurchaseService $purchases, Event $event, Ticket $ticket, int $qty): TicketOrder
{
    return $purchases->createOrder([
        'event_id' => $event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => 'buyer@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => $qty]],
    ]);
}

/**
 * Buy out the whole stock of a PAID ticket via a mocked gateway checkout,
 * WITHOUT confirming it - the order stays PendingPayment (mirrors a real
 * buyer who hasn't paid yet), so expireOrder() has something to expire.
 */
function sellOutPending(TicketPurchaseService $purchases, Event $event, Ticket $ticket, int $qty): TicketOrder
{
    ProjectPaymentGateway::factory()->create([
        'project_id' => $event->project_id,
        'mode' => 'test',
        'is_active' => true,
    ]);

    $client = mock(CreatesCheckout::class);
    $client->shouldReceive('createCheckout')->once()->andReturn([
        'reference' => 'ref_waitlist_'.Str::random(8),
        'payment_url' => 'https://pay.example/waitlist-test',
        'checkout_method' => 'payment_link_legacy',
    ]);
    $client->shouldReceive('gateway')->andReturnNull();

    return $purchases->createOrder([
        'event_id' => $event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => 'buyer@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => $qty]],
    ], $client);
}

// ─── Step 1: join / dedupe ────────────────────────────────────────────────

it('joining a sold-out ticket creates a waiting entry, and a duplicate email is idempotent', function () {
    Mail::fake();
    $ticket = waitlistTicket($this->event, 1);

    $entry = $this->waitlist->join([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'email' => 'Waiter@Example.com',
        'name' => 'Waiter',
        'phone' => '0811',
        'quantity' => 2,
    ]);

    expect($entry->status)->toBe(TicketWaitlistEntryStatus::Waiting)
        ->and($entry->email)->toBe('waiter@example.com')
        ->and($entry->quantity)->toBe(2)
        ->and($entry->position)->toBe(1)
        ->and(TicketWaitlistEntry::count())->toBe(1);

    // Re-submitting the same email (any-case) while still waiting is a no-op:
    // same row, unchanged quantity - not a second entry, not a queue jump.
    $again = $this->waitlist->join([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'email' => 'waiter@example.com',
        'quantity' => 5,
    ]);

    expect($again->id)->toBe($entry->id)
        ->and($again->quantity)->toBe(2)
        ->and(TicketWaitlistEntry::count())->toBe(1);
});

// ─── Step 2: offer on release (the correctness-critical mechanism) ────────

it('offers a released seat to the next waitlist entry when a pending order expires, genuinely holding it via an atomic reserve', function () {
    Mail::fake();
    $ticket = waitlistTicket($this->event, 1, 50000);
    $order = sellOutPending($this->purchases, $this->event, $ticket, 1);
    expect($ticket->fresh()->sold_count)->toBe(1);

    $entry = $this->waitlist->join([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'email' => 'waiter@example.com',
        'quantity' => 1,
    ]);

    // The `sync` queue driver runs OfferWaitlistSeatsJob (dispatched
    // ->afterCommit() inside expireOrder()) end-to-end right here, so by the
    // time expireOrder() returns the hook has already released AND
    // re-offered the seat.
    $this->purchases->expireOrder($order->fresh());

    // THE critical assertion: sold_count is back at 1 (not left at 0) - the
    // seat was genuinely RE-RESERVED for the waitlist entry
    // (Ticket::reserve()), not merely promised. Two claimers can no longer
    // race for it.
    expect($ticket->fresh()->sold_count)->toBe(1)
        ->and($entry->fresh()->status)->toBe(TicketWaitlistEntryStatus::Offered)
        ->and($entry->fresh()->claim_token)->not->toBeNull()
        ->and($entry->fresh()->offer_expires_at)->not->toBeNull();
});

it('offers a released seat to the waitlist when an attendee is refunded', function () {
    Mail::fake();
    $ticket = waitlistTicket($this->event, 1);
    $order = sellOutFree($this->purchases, $this->event, $ticket, 1);
    expect($ticket->fresh()->sold_count)->toBe(1);

    $entry = $this->waitlist->join([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'email' => 'waiter@example.com',
        'quantity' => 1,
    ]);

    $attendee = $order->attendees->first();
    // Runs end-to-end on the `sync` queue driver (see the expireOrder test
    // above): the hook releases the refunded seat and re-offers it in the
    // same call.
    $this->purchases->refundAttendee($attendee);

    expect($ticket->fresh()->sold_count)->toBe(1)
        ->and($entry->fresh()->status)->toBe(TicketWaitlistEntryStatus::Offered);
});

it('dispatches OfferWaitlistSeatsJob for the freed quantity when a pending order expires', function () {
    Bus::fake();
    $ticket = waitlistTicket($this->event, 1, 50000);
    $order = sellOutPending($this->purchases, $this->event, $ticket, 1);

    $this->purchases->expireOrder($order->fresh());

    Bus::assertDispatched(
        OfferWaitlistSeatsJob::class,
        fn (OfferWaitlistSeatsJob $job): bool => $job->ticketId === $ticket->id && $job->quantity === 1,
    );
});

it('dispatches OfferWaitlistSeatsJob for the freed quantity when an attendee is refunded', function () {
    Bus::fake();
    $ticket = waitlistTicket($this->event, 1);
    $order = sellOutFree($this->purchases, $this->event, $ticket, 1);

    $this->purchases->refundAttendee($order->attendees->first());

    Bus::assertDispatched(
        OfferWaitlistSeatsJob::class,
        fn (OfferWaitlistSeatsJob $job): bool => $job->ticketId === $ticket->id && $job->quantity === 1,
    );
});

// ─── Step 3: claim -> order ────────────────────────────────────────────────

it('claims a held offer within the window and creates the order for the held quantity without oversell', function () {
    Mail::fake();
    $ticket = waitlistTicket($this->event, 1, 50000);
    $order = sellOutPending($this->purchases, $this->event, $ticket, 1);
    $entry = $this->waitlist->join([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'email' => 'waiter@example.com',
        'name' => 'Waiter',
        'phone' => '0811',
        'quantity' => 1,
    ]);

    $this->purchases->expireOrder($order->fresh());
    $this->waitlist->offerReleasedSeats($ticket->fresh(), 1);
    $token = $entry->fresh()->claim_token;
    expect($token)->not->toBeNull();

    $claimedOrder = $this->waitlist->claim($token);

    expect($claimedOrder)->toBeInstanceOf(TicketOrder::class)
        ->and($claimedOrder->buyer_email)->toBe('waiter@example.com')
        ->and($claimedOrder->items()->first()->quantity)->toBe(1)
        ->and($claimedOrder->attendees()->count())->toBe(1)
        ->and($entry->fresh()->status)->toBe(TicketWaitlistEntryStatus::Claimed)
        // Still exactly 1, not 2: claim() consumed the existing hold from
        // offerReleasedSeats() instead of reserving the seat a second time.
        ->and($ticket->fresh()->sold_count)->toBe(1);

    // No oversell: the ticket's single seat is fully accounted for between
    // the original sale and this claim - nothing left to reserve.
    expect($ticket->fresh()->reserve(1))->toBeFalse();
});

it('fails to claim an expired offer and re-offers the seat to the next waiting entry', function () {
    Mail::fake();
    $ticket = waitlistTicket($this->event, 1, 50000);
    $order = sellOutPending($this->purchases, $this->event, $ticket, 1);

    $entryA = $this->waitlist->join([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'email' => 'first@example.com', 'quantity' => 1,
    ]);
    $entryB = $this->waitlist->join([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'email' => 'second@example.com', 'quantity' => 1,
    ]);

    $this->purchases->expireOrder($order->fresh());
    $this->waitlist->offerReleasedSeats($ticket->fresh(), 1);

    expect($entryA->fresh()->status)->toBe(TicketWaitlistEntryStatus::Offered)
        ->and($entryB->fresh()->status)->toBe(TicketWaitlistEntryStatus::Waiting);

    $expiredToken = $entryA->fresh()->claim_token;
    $entryA->fresh()->forceFill(['offer_expires_at' => now()->subMinute()])->save();

    expect(fn () => $this->waitlist->claim($expiredToken))->toThrow(HttpException::class);
    // The failed claim attempt must not have consumed or double-reserved anything.
    expect($ticket->fresh()->sold_count)->toBe(1)
        ->and($entryA->fresh()->status)->toBe(TicketWaitlistEntryStatus::Offered);

    $expiredCount = $this->waitlist->expireStaleOffers();

    expect($expiredCount)->toBe(1)
        ->and($entryA->fresh()->status)->toBe(TicketWaitlistEntryStatus::Expired)
        ->and($entryB->fresh()->status)->toBe(TicketWaitlistEntryStatus::Offered)
        ->and($entryB->fresh()->claim_token)->not->toBeNull()
        ->and($entryB->fresh()->claim_token)->not->toBe($expiredToken)
        // Seat handed to entry B, still held exactly once.
        ->and($ticket->fresh()->sold_count)->toBe(1);
});

// ─── Step 4: expiry sweep + notify_only fallback ──────────────────────────

it('expireStaleOffers releases the held seat back to the pool when nobody is left waiting', function () {
    Mail::fake();
    $ticket = waitlistTicket($this->event, 1, 50000);
    $order = sellOutPending($this->purchases, $this->event, $ticket, 1);
    $entry = $this->waitlist->join([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'email' => 'waiter@example.com', 'quantity' => 1,
    ]);

    $this->purchases->expireOrder($order->fresh());
    $this->waitlist->offerReleasedSeats($ticket->fresh(), 1);
    expect($ticket->fresh()->sold_count)->toBe(1);

    $entry->fresh()->forceFill(['offer_expires_at' => now()->subMinute()])->save();

    $expiredCount = $this->waitlist->expireStaleOffers();

    expect($expiredCount)->toBe(1)
        ->and($entry->fresh()->status)->toBe(TicketWaitlistEntryStatus::Expired)
        ->and($ticket->fresh()->sold_count)->toBe(0);
});

it('notify_only mode emails the waitlist entry without holding a seat', function () {
    Bus::fake();
    $this->event->update(['waitlist_mode' => WaitlistMode::NotifyOnly]);

    $ticket = waitlistTicket($this->event, 1, 50000);
    $order = sellOutPending($this->purchases, $this->event, $ticket, 1);
    $entry = $this->waitlist->join([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'email' => 'waiter@example.com', 'quantity' => 1,
    ]);

    $this->purchases->expireOrder($order->fresh());
    expect($ticket->fresh()->sold_count)->toBe(0);

    $processed = $this->waitlist->offerReleasedSeats($ticket->fresh()->load('event'), 1);

    expect($processed)->toBe(1)
        ->and($entry->fresh()->status)->toBe(TicketWaitlistEntryStatus::Waiting)
        ->and($entry->fresh()->claim_token)->toBeNull()
        // No hold: notify_only never calls Ticket::reserve().
        ->and($ticket->fresh()->sold_count)->toBe(0);

    Bus::assertDispatched(
        SendWaitlistAvailableNotifyJob::class,
        fn (SendWaitlistAvailableNotifyJob $job): bool => $job->waitlistEntryId === $entry->id,
    );
});
