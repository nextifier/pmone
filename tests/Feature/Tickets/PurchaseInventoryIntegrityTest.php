<?php

use App\Models\Attendee;
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
function priceableTicket(Event $event, float $price, ?int $stock = null, array $overrides = []): Ticket
{
    $ticket = Ticket::factory()->create(array_merge(['event_id' => $event->id, 'stock' => $stock], $overrides));
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
