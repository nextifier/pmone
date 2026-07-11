<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
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

// ─── Trigger A: quota enforcement ────────────────────────────────────────────

it('buyer past phase quota does not get the phase price', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);
    $phase = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 10000,
        'quota' => 2,
        'sold_count' => 2,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);

    expect($phase->fresh()->sold_count)->toBe(2);
});

it('rejects a purchase quantity that would exceed the remaining phase quota', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 10000,
        'quota' => 5,
        'sold_count' => 3,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    // 2 remain, but the buyer wants 3 - the whole line is rejected rather
    // than quietly capped or charged from the sold-out phase.
    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]))->toThrow(HttpException::class);
});

it('sold-out phase falls through to next active phase', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);
    $soldOut = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'label' => 'Early Bird',
        'price' => 10000,
        'quota' => 2,
        'sold_count' => 2,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
    $fallback = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'label' => 'Regular',
        'price' => 20000,
        'quota' => null,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    $item = $order->items()->first();
    expect((float) $item->unit_price)->toBe(20000.0)
        ->and($item->phase_label)->toBe('Regular')
        ->and($fallback->fresh()->sold_count)->toBe(1)
        ->and($soldOut->fresh()->sold_count)->toBe(2);
});

it('allows a purchase exactly at the remaining phase quota', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);
    $phase = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 10000,
        'quota' => 5,
        'sold_count' => 3,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    expect((float) $order->items()->first()->unit_price)->toBe(10000.0)
        ->and($phase->fresh()->sold_count)->toBe(5);
});
