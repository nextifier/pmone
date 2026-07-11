<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
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
function priceableTicket(Event $event, float $price, ?int $stock = null, array $overrides = []): Ticket
{
    // max_quantity defaults to null (uncapped) so multi-line aggregate
    // quantities in these tests aren't flaky against the factory's random
    // optional() cap; tests that care about max_quantity override it.
    $ticket = Ticket::factory()->create(array_merge(['event_id' => $event->id, 'stock' => $stock, 'max_quantity' => null], $overrides));
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

// Trigger A: duplicate-line oversell.

it('rejects a duplicate-line payload that would jointly oversell a ticket', function () {
    $ticket = priceableTicket($this->event, 0, stock: 1);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticket->id, 'quantity' => 1],
            ['ticket_id' => $ticket->id, 'quantity' => 1],
        ],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::count())->toBe(0)
        ->and(Attendee::count())->toBe(0)
        ->and($ticket->fresh()->sold_count)->toBe(0);
});

it('rejects an aggregated per-ticket quantity above max_quantity even though each line is individually within it', function () {
    $ticket = priceableTicket($this->event, 0, stock: 100, overrides: ['max_quantity' => 5]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticket->id, 'quantity' => 3],
            ['ticket_id' => $ticket->id, 'quantity' => 3],
        ],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::count())->toBe(0);
});

it('validates availability independently per ticket across a mixed multi-ticket, multi-line cart', function () {
    $ticketA = priceableTicket($this->event, 0, stock: 5);
    $ticketB = priceableTicket($this->event, 0, stock: 10);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticketA->id, 'quantity' => 2],
            ['ticket_id' => $ticketA->id, 'quantity' => 3],
            ['ticket_id' => $ticketB->id, 'quantity' => 4],
        ],
    ]);

    expect($order->attendees()->count())->toBe(9)
        ->and($ticketA->fresh()->sold_count)->toBe(5)
        ->and($ticketB->fresh()->sold_count)->toBe(4);
});

it('rejects the same mixed cart when one of the two tickets lacks aggregated stock', function () {
    $ticketA = priceableTicket($this->event, 0, stock: 4); // 2 + 3 = 5 requested, only 4 available
    $ticketB = priceableTicket($this->event, 0, stock: 10);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticketA->id, 'quantity' => 2],
            ['ticket_id' => $ticketA->id, 'quantity' => 3],
            ['ticket_id' => $ticketB->id, 'quantity' => 4],
        ],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::count())->toBe(0);
});

// Trigger B: comp batches must not eat public stock.

it('does not let a comp batch reduce public availableStock', function () {
    $ticket = priceableTicket($this->event, 0, stock: 5);

    $this->service->bulkGenerate([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'mode' => 'anonymous',
        'quantity' => 5,
    ]);

    expect($this->service->availableStock($ticket->fresh()))->toBe(5);

    // The public can still buy the full stock even though a comp batch of
    // the same size was just confirmed against this ticket.
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 5]],
    ]);

    expect($order->attendees()->count())->toBe(5);
});

// Trigger C: a deactivated session must not be bookable.

it('rejects ordering a session that an admin has deactivated', function () {
    $addOn = Ticket::factory()->addOn()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $addOn->id,
        'price' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
    $session = TicketSession::factory()->create(['ticket_id' => $addOn->id, 'capacity' => 10, 'is_active' => false]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $addOn->id, 'quantity' => 1, 'ticket_session_id' => $session->id]],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::count())->toBe(0)
        ->and($session->fresh()->booked_count)->toBe(0);
});
