<?php

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
 * SQLite in tests cannot truly run parallel/concurrent transactions, so
 * these prove the atomic-conditional-UPDATE semantics directly instead: N
 * sequential attempts against a stock-of-N ticket succeed exactly N times
 * and reject the rest, with the counter landing exactly on N - never over.
 * Because each reserve() is a single `UPDATE ... WHERE sold_count + ? <=
 * stock` statement, this is the same guarantee real concurrent connections
 * get from Postgres row-level write serialization on that UPDATE - but a
 * genuine concurrency load test against Postgres (many actual parallel
 * connections/processes) is still required pre-launch; see plan 016's
 * maintenance notes and the k6 command it specifies.
 */
it('Ticket::reserve lets exactly `stock` of many attempts on the last seats succeed, never oversold', function () {
    $stock = 10;
    $attempts = $stock + 5;
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => $stock]);

    $succeeded = 0;
    $failed = 0;
    for ($i = 0; $i < $attempts; $i++) {
        if ($ticket->reserve(1)) {
            $succeeded++;
        } else {
            $failed++;
        }
    }

    expect($succeeded)->toBe($stock)
        ->and($failed)->toBe($attempts - $stock)
        ->and($ticket->fresh()->sold_count)->toBe($stock);
});

it('TicketPricePhase::reserve lets exactly `quota` of many attempts succeed, never oversold', function () {
    $quota = 7;
    $attempts = $quota + 5;
    $phase = TicketPricePhase::factory()->create(['quota' => $quota]);

    $succeeded = 0;
    for ($i = 0; $i < $attempts; $i++) {
        if ($phase->reserve(1)) {
            $succeeded++;
        }
    }

    expect($succeeded)->toBe($quota)
        ->and($phase->fresh()->sold_count)->toBe($quota);
});

it('TicketSession::reserve lets exactly `capacity` of many attempts succeed, never oversold', function () {
    $capacity = 4;
    $attempts = $capacity + 5;
    $session = TicketSession::factory()->create(['capacity' => $capacity]);

    $succeeded = 0;
    for ($i = 0; $i < $attempts; $i++) {
        if ($session->reserve(1)) {
            $succeeded++;
        }
    }

    expect($succeeded)->toBe($capacity)
        ->and($session->fresh()->booked_count)->toBe($capacity);
});

// ─── End-to-end through createOrder(): the whole hot path never oversells ───

it('createOrder lets exactly `stock` single-seat purchases of the last ticket succeed and 422s the rest, sold_count landing exactly on stock', function () {
    $stock = 5;
    $attempts = $stock + 5;
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => $stock, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0, 'quota' => null,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $succeeded = 0;
    $failed = 0;
    for ($i = 0; $i < $attempts; $i++) {
        try {
            $this->service->createOrder([
                'event_id' => $this->event->id,
                'buyer_name' => "Buyer {$i}", 'buyer_email' => "buyer{$i}@example.com", 'buyer_phone' => '08',
                'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
            ]);
            $succeeded++;
        } catch (HttpException $e) {
            $failed++;
        }
    }

    expect($succeeded)->toBe($stock)
        ->and($failed)->toBe($attempts - $stock)
        ->and($ticket->fresh()->sold_count)->toBe($stock)
        ->and(TicketOrder::count())->toBe($stock);
});

it('createOrder never oversells a phase quota even when the ticket stock has plenty of room', function () {
    $quota = 3;
    $attempts = $quota + 5;
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 1000, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0, 'quota' => $quota,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $succeeded = 0;
    for ($i = 0; $i < $attempts; $i++) {
        try {
            $this->service->createOrder([
                'event_id' => $this->event->id,
                'buyer_name' => "Buyer {$i}", 'buyer_email' => "buyer{$i}@example.com", 'buyer_phone' => '08',
                'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
            ]);
            $succeeded++;
        } catch (HttpException $e) {
            // sold out for this phase - expected once quota is exhausted.
        }
    }

    expect($succeeded)->toBe($quota)
        ->and($ticket->fresh()->sold_count)->toBe($quota);
});

// ─── Partial-reservation rollback: a later line's failure releases earlier lines' holds ───

it('releases an already-reserved ticket/phase when a later line in the same cart fails on session capacity', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 100, 'max_quantity' => null]);
    $phase = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0, 'quota' => 100,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $addOn = Ticket::factory()->addOn()->create(['event_id' => $this->event->id, 'stock' => null, 'max_quantity' => null]);
    $addOnPhase = TicketPricePhase::factory()->create([
        'ticket_id' => $addOn->id, 'price' => 0, 'quota' => null,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);
    $fullSession = TicketSession::factory()->create(['ticket_id' => $addOn->id, 'capacity' => 1]);

    // Fill the add-on's only session first.
    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'First', 'buyer_email' => 'first@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $addOn->id, 'quantity' => 1, 'ticket_session_id' => $fullSession->id]],
    ]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Second', 'buyer_email' => 'second@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $ticket->id, 'quantity' => 5],
            ['ticket_id' => $addOn->id, 'quantity' => 1, 'ticket_session_id' => $fullSession->id],
        ],
    ]))->toThrow(HttpException::class);

    // The whole cart is rejected (the session is full) - the FIRST line's
    // ticket + phase reservation, and the second line's own ticket/phase
    // reservation (both of which succeeded before the session check failed),
    // must all have been released rather than left stranded.
    expect($ticket->fresh()->sold_count)->toBe(0)
        ->and($phase->fresh()->sold_count)->toBe(0)
        ->and($addOn->fresh()->sold_count)->toBe(1) // only the first buyer's seat
        ->and($addOnPhase->fresh()->sold_count)->toBe(1)
        ->and(TicketOrder::where('buyer_email', 'second@example.com')->count())->toBe(0);
});
