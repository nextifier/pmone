<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function capacityTestTicket(Event $event, float $price, ?int $stock = null, array $overrides = []): Ticket
{
    // max_quantity defaults to null (uncapped) so multi-line aggregate
    // quantities in these tests aren't flaky against the factory's random
    // optional() cap - mirrors PurchaseInventoryIntegrityTest's helper.
    $ticket = Ticket::factory()->create(array_merge(['event_id' => $event->id, 'stock' => $stock, 'max_quantity' => null], $overrides));
    // quota pinned to null (unlimited) for the same reason: the factory's
    // default is fake()->optional()->numberBetween(10, 200), which the
    // 500-qty "uncapped" test below would intermittently exceed.
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'quota' => null,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

// ─── Step 1: Event::reserveHeadcount/releaseHeadcount atomic counter ─────

it('Event::reserveHeadcount succeeds within capacity and fails when it would exceed it', function () {
    $event = Event::factory()->create(['project_id' => $this->project->id, 'capacity' => 5]);

    expect($event->reserveHeadcount(5))->toBeTrue()
        ->and($event->fresh()->reserved_count)->toBe(5)
        ->and($event->reserveHeadcount(1))->toBeFalse()
        ->and($event->fresh()->reserved_count)->toBe(5);
});

it('Event::reserveHeadcount is unlimited when capacity is null', function () {
    $event = Event::factory()->create(['project_id' => $this->project->id, 'capacity' => null]);

    expect($event->reserveHeadcount(10000))->toBeTrue()
        ->and($event->fresh()->reserved_count)->toBe(10000);
});

it('Event::releaseHeadcount never decrements reserved_count below zero', function () {
    $event = Event::factory()->create(['project_id' => $this->project->id, 'capacity' => 5]);
    $event->forceFill(['reserved_count' => 1])->save();

    $event->releaseHeadcount(5);

    expect($event->fresh()->reserved_count)->toBe(1);
});

it('does not leak a reservation onto another event - guards the parenthesization of the capacity clause', function () {
    // Regression guard for the exact bug Ticket::reserve()'s comment warns
    // about: without parens around "(capacity IS NULL OR reserved_count + ?
    // <= capacity)", SQL's AND-before-OR precedence turns the WHERE into
    // "(id = ? AND capacity IS NULL) OR (reserved_count + ? <= capacity)" -
    // the second branch has no id restriction at all, so it would match (and
    // reserve headcount on) ANY other roomy event in the table.
    $fullEvent = Event::factory()->create(['project_id' => $this->project->id, 'capacity' => 5]);
    $fullEvent->forceFill(['reserved_count' => 5])->save();
    $roomyEvent = Event::factory()->create(['project_id' => $this->project->id, 'capacity' => 100]);

    expect($fullEvent->reserveHeadcount(1))->toBeFalse()
        ->and($fullEvent->fresh()->reserved_count)->toBe(5)
        ->and($roomyEvent->fresh()->reserved_count)->toBe(0);
});

// ─── Step 2: wired into createOrder + expire/refund releases ─────────────

it('rejects a mixed-ticket cart that would push the event past its total-headcount capacity', function () {
    $this->event->update(['capacity' => 5]);
    $ticketA = capacityTestTicket($this->event, 0, stock: 10);
    $ticketB = capacityTestTicket($this->event, 0, stock: 10);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticketA->id, 'quantity' => 3],
            ['ticket_id' => $ticketB->id, 'quantity' => 3],
        ],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::count())->toBe(0)
        ->and($ticketA->fresh()->sold_count)->toBe(0)
        ->and($ticketB->fresh()->sold_count)->toBe(0)
        ->and($this->event->fresh()->reserved_count)->toBe(0);
});

it('reserves exactly the total requested headcount across a mixed-ticket cart when within capacity', function () {
    $this->event->update(['capacity' => 10]);
    $ticketA = capacityTestTicket($this->event, 0, stock: 10);
    $ticketB = capacityTestTicket($this->event, 0, stock: 10);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticketA->id, 'quantity' => 4],
            ['ticket_id' => $ticketB->id, 'quantity' => 3],
        ],
    ]);

    expect($order->attendees()->count())->toBe(7)
        ->and($this->event->fresh()->reserved_count)->toBe(7);
});

it('releases the event headcount hold when a later per-ticket reservation fails', function () {
    $this->event->update(['capacity' => 100]);
    $ticketA = capacityTestTicket($this->event, 0, stock: 10);
    $ticketB = capacityTestTicket($this->event, 0, stock: 2); // not enough for the requested 3

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticketA->id, 'quantity' => 3],
            ['ticket_id' => $ticketB->id, 'quantity' => 3],
        ],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::count())->toBe(0)
        ->and($ticketA->fresh()->sold_count)->toBe(0)
        ->and($this->event->fresh()->reserved_count)->toBe(0);
});

it('createOrder never checks the event headcount when capacity is null (uncapped)', function () {
    $ticket = capacityTestTicket($this->event, 0, stock: 1000);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 500]],
    ]);

    expect($order->attendees()->count())->toBe(500)
        ->and($this->event->fresh()->reserved_count)->toBe(500);
});

it('releases the event headcount when a pending order expires', function () {
    $this->event->update(['capacity' => 10]);
    // A nonzero price keeps the order PendingPayment after creation (a free
    // order confirms immediately) so expireOrder() below has something to act on.
    $ticket = capacityTestTicket($this->event, 10000, stock: 10);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    expect($order->status)->toBe(TicketOrderStatus::PendingPayment)
        ->and($this->event->fresh()->reserved_count)->toBe(3);

    $this->service->expireOrder($order->fresh());

    expect($this->event->fresh()->reserved_count)->toBe(0);
});

it('releases one seat of the event headcount when a single attendee is refunded', function () {
    $this->event->update(['capacity' => 10]);
    $ticket = capacityTestTicket($this->event, 0, stock: 10);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    expect($this->event->fresh()->reserved_count)->toBe(3);

    $attendee = $order->attendees()->first();
    $this->service->refundAttendee($attendee);

    expect($this->event->fresh()->reserved_count)->toBe(2);
});

it('releases the full event headcount when an entire order is refunded', function () {
    $this->event->update(['capacity' => 10]);
    $ticket = capacityTestTicket($this->event, 0, stock: 10);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    expect($this->event->fresh()->reserved_count)->toBe(3);

    $this->service->refundOrder($order->fresh());

    expect($this->event->fresh()->reserved_count)->toBe(0);
});

// ─── STOP condition (plan 021): bulkGenerate is intentionally unwired ────

it('does not reserve event headcount for a comp/bulk-generated batch', function () {
    $this->event->update(['capacity' => 5]);
    $ticket = capacityTestTicket($this->event, 0, stock: 100);

    $this->service->bulkGenerate([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'mode' => 'anonymous',
        'quantity' => 50, // deliberately over the event capacity
    ]);

    expect($this->event->fresh()->reserved_count)->toBe(0);
});

// ─── reconfirmAfterExpiry: late payment restores / reconciles headcount ──

it('restores the event headcount when an expired order is reconfirmed after a late payment', function () {
    $this->event->update(['capacity' => 10]);
    // Nonzero price keeps the order PendingPayment so it can expire, then be
    // resurrected by a late payment.
    $ticket = capacityTestTicket($this->event, 10000, stock: 10);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    $this->service->expireOrder($order->fresh());
    expect($this->event->fresh()->reserved_count)->toBe(0);

    $result = $this->service->reconfirmAfterExpiry($order->fresh());

    expect($result)->toBe('reconfirmed')
        ->and($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($this->event->fresh()->reserved_count)->toBe(3);
});

it('routes a late payment to reconciliation when the event has since filled to capacity', function () {
    $this->event->update(['capacity' => 5]);
    $ticket = capacityTestTicket($this->event, 10000, stock: 100);

    // Order 1 holds 3 seats, then expires (releasing them back to the event).
    $order1 = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);
    $this->service->expireOrder($order1->fresh());
    expect($this->event->fresh()->reserved_count)->toBe(0);

    // The freed slots are taken by another buyer, filling the event to its cap.
    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'B', 'buyer_email' => 'b@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 5]],
    ]);
    expect($this->event->fresh()->reserved_count)->toBe(5);

    // Order 1's late payment can no longer fit (5 + 3 > 5): reconcile, do not
    // auto-confirm into an over-full venue, and leave reserved_count untouched.
    $result = $this->service->reconfirmAfterExpiry($order1->fresh());

    expect($result)->toBe('needs_reconciliation')
        ->and($order1->fresh()->status)->toBe(TicketOrderStatus::Expired)
        ->and($this->event->fresh()->reserved_count)->toBe(5);
});
