<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
});

// ─── Step 1: atomic conditional reserve()/release() on each counter ──────

it('Ticket::reserve succeeds within stock and fails when it would exceed it', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);

    expect($ticket->reserve(5))->toBeTrue()
        ->and($ticket->fresh()->sold_count)->toBe(5)
        ->and($ticket->reserve(1))->toBeFalse()
        ->and($ticket->fresh()->sold_count)->toBe(5);
});

it('Ticket::reserve is unlimited when stock is null', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);

    expect($ticket->reserve(10000))->toBeTrue()
        ->and($ticket->fresh()->sold_count)->toBe(10000);
});

it('Ticket::release never decrements sold_count below zero', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $ticket->forceFill(['sold_count' => 1])->save();

    $ticket->release(5);

    expect($ticket->fresh()->sold_count)->toBe(1);
});

it('TicketPricePhase::reserve succeeds within quota and fails when it would exceed it', function () {
    $phase = TicketPricePhase::factory()->create(['quota' => 3]);

    expect($phase->reserve(3))->toBeTrue()
        ->and($phase->fresh()->sold_count)->toBe(3)
        ->and($phase->reserve(1))->toBeFalse()
        ->and($phase->fresh()->sold_count)->toBe(3);
});

it('TicketPricePhase::reserve is unlimited when quota is null', function () {
    $phase = TicketPricePhase::factory()->create(['quota' => null]);

    expect($phase->reserve(500))->toBeTrue()
        ->and($phase->fresh()->sold_count)->toBe(500);
});

it('TicketPricePhase::release never decrements sold_count below zero', function () {
    $phase = TicketPricePhase::factory()->create(['quota' => 3]);
    $phase->forceFill(['sold_count' => 1])->save();

    $phase->release(5);

    expect($phase->fresh()->sold_count)->toBe(1);
});

it('TicketSession::reserve succeeds within capacity and fails when it would exceed it', function () {
    $session = TicketSession::factory()->create(['capacity' => 2]);

    expect($session->reserve(2))->toBeTrue()
        ->and($session->fresh()->booked_count)->toBe(2)
        ->and($session->reserve(1))->toBeFalse()
        ->and($session->fresh()->booked_count)->toBe(2);
});

it('TicketSession::reserve is unlimited when capacity is null', function () {
    $session = TicketSession::factory()->create(['capacity' => null]);

    expect($session->reserve(50))->toBeTrue()
        ->and($session->fresh()->booked_count)->toBe(50);
});

it('TicketSession::release never decrements booked_count below zero', function () {
    $session = TicketSession::factory()->create(['capacity' => 2]);
    $session->forceFill(['booked_count' => 1])->save();

    $session->release(5);

    expect($session->fresh()->booked_count)->toBe(1);
});

it('rejects a multi-seat reservation outright when it would exceed remaining stock, never partially fulfilling it', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 10]);

    expect($ticket->reserve(3))->toBeTrue()
        ->and($ticket->reserve(3))->toBeTrue()
        ->and($ticket->reserve(3))->toBeTrue()
        ->and($ticket->fresh()->sold_count)->toBe(9)
        // Would land on 12 > 10 - rejected outright, not capped to the 1 seat
        // that remains.
        ->and($ticket->reserve(3))->toBeFalse()
        ->and($ticket->fresh()->sold_count)->toBe(9);
});

// ─── Step 3: availableStock/availableSessionCapacity read the counter directly ───

it('availableStock reflects sold_count directly, with no order rows involved', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 10]);
    $ticket->forceFill(['sold_count' => 4])->save();

    // No TicketOrder/TicketOrderItem rows exist at all - the old SUM-based
    // committedQuantity() would have returned 0 (no committed rows) here;
    // the counter read correctly reflects the reservation regardless.
    expect(TicketOrder::count())->toBe(0)
        ->and($this->service->availableStock($ticket->fresh()))->toBe(6);
});

it('availableStock is null (unlimited) when stock is null regardless of sold_count', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);
    $ticket->forceFill(['sold_count' => 999])->save();

    expect($this->service->availableStock($ticket->fresh()))->toBeNull();
});

it('availableSessionCapacity reflects booked_count directly, with no order rows involved', function () {
    $session = TicketSession::factory()->create(['capacity' => 10]);
    $session->forceFill(['booked_count' => 3])->save();

    expect($this->service->availableSessionCapacity($session->fresh()))->toBe(7);
});

it('the hot-path availability read no longer runs a SUM over ticket_order_items', function () {
    // Guards against a regression re-introducing the SUM-based
    // committedQuantity() read on the availability hot path.
    $source = file_get_contents(app_path('Services/Ticket/TicketPurchaseService.php'));

    expect($source)->not->toContain("sum('toi.quantity')")
        ->and($source)->not->toContain('committedQuantity');
});

// ─── Step 4: counter-integrity across create -> expire -> reconfirm -> refund ───

it('keeps ticket, phase, and session counters exact across create -> expire -> reconfirm -> refund', function () {
    $ticket = Ticket::factory()->addOn()->create(['event_id' => $this->event->id, 'stock' => 10, 'max_quantity' => null]);
    $phase = TicketPricePhase::factory()->create([
        // A nonzero price keeps the order PendingPayment after creation
        // (a free order confirms immediately) so expireOrder() below has
        // something to act on.
        'ticket_id' => $ticket->id, 'price' => 10000, 'quota' => 10,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);
    $session = TicketSession::factory()->create(['ticket_id' => $ticket->id, 'capacity' => 10]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3, 'ticket_session_id' => $session->id]],
    ]);

    expect($order->status)->toBe(TicketOrderStatus::PendingPayment);

    expect($ticket->fresh()->sold_count)->toBe(3)
        ->and($phase->fresh()->sold_count)->toBe(3)
        ->and($session->fresh()->booked_count)->toBe(3);

    $this->service->expireOrder($order->fresh());

    expect($ticket->fresh()->sold_count)->toBe(0)
        ->and($phase->fresh()->sold_count)->toBe(0)
        ->and($session->fresh()->booked_count)->toBe(0);

    $outcome = $this->service->reconfirmAfterExpiry($order->fresh(), ['id' => 'inv_atomic_reconfirm']);

    expect($outcome)->toBe('reconfirmed')
        ->and($ticket->fresh()->sold_count)->toBe(3)
        ->and($phase->fresh()->sold_count)->toBe(3)
        ->and($session->fresh()->booked_count)->toBe(3);

    $this->service->refundOrder($order->fresh());

    expect($ticket->fresh()->sold_count)->toBe(0)
        ->and($phase->fresh()->sold_count)->toBe(0)
        ->and($session->fresh()->booked_count)->toBe(0);
});

it('refundAttendee releases exactly one seat from a multi-seat order, leaving the rest of the counters intact', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 10, 'max_quantity' => null]);
    $phase = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0, 'quota' => 10,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    expect($ticket->fresh()->sold_count)->toBe(3)
        ->and($phase->fresh()->sold_count)->toBe(3);

    $attendee = $order->attendees()->first();
    $this->service->refundAttendee($attendee);

    expect($ticket->fresh()->sold_count)->toBe(2)
        ->and($phase->fresh()->sold_count)->toBe(2);
});
