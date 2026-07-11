<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\AccessCode;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

// ─── Trigger B: phase sold_count symmetry on expire / reconfirm ─────────────

it('expiring an order releases the phase sold_count', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);
    $phase = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 10000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    expect($phase->fresh()->sold_count)->toBe(2)
        ->and($order->items()->first()->ticket_price_phase_id)->toBe($phase->id);

    $this->service->expireOrder($order);

    expect($phase->fresh()->sold_count)->toBe(0)
        ->and($order->fresh()->status)->toBe(TicketOrderStatus::Expired);
});

it('reconfirmAfterExpiry re-increments the phase sold_count it previously released', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);
    $phase = TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 10000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    $this->service->expireOrder($order);
    expect($phase->fresh()->sold_count)->toBe(0);

    $outcome = $this->service->reconfirmAfterExpiry($order->fresh(), ['id' => 'inv_late']);

    expect($outcome)->toBe('reconfirmed')
        ->and($phase->fresh()->sold_count)->toBe(2);
});

// ─── Trigger C: preview must not price hidden/inactive tickets ──────────────

it('preview does not price a hidden ticket without a valid access code', function () {
    $ticket = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'visibility' => 'hidden',
    ]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 50000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $preview = $this->service->previewCart(
        $this->event,
        [['ticket_id' => $ticket->id, 'quantity' => 1]],
    );

    expect($preview['lines'])->toBeEmpty()
        ->and($preview['subtotal'])->toBe(0.0);
});

it('preview prices a hidden ticket when a valid access code unlocks it', function () {
    $ticket = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'visibility' => 'hidden',
    ]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 50000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $code = AccessCode::factory()->create([
        'event_id' => $this->event->id,
        'code' => 'UNLOCKME',
    ]);
    $code->unlocks()->attach($ticket->id);

    $preview = $this->service->previewCart(
        $this->event,
        [['ticket_id' => $ticket->id, 'quantity' => 1]],
        null,
        null,
        'UNLOCKME',
    );

    expect($preview['lines'])->toHaveCount(1)
        ->and($preview['subtotal'])->toBe(50000.0);
});

it('preview does not price an inactive ticket', function () {
    $ticket = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'is_active' => false,
    ]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 50000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $preview = $this->service->previewCart(
        $this->event,
        [['ticket_id' => $ticket->id, 'quantity' => 1]],
    );

    expect($preview['lines'])->toBeEmpty()
        ->and($preview['subtotal'])->toBe(0.0);
});

// ─── Trigger D: phase windows vs purchase-time "now" agree on one timezone ──

it('evaluates phase windows in the same timezone the admin phase form submits, so no UTC/local mismatch exists', function () {
    // Investigation (see plan 004 report): the admin phase form
    // (frontend/app/components/ticket/PricePhasesPanel.vue) reads a plain
    // browser-local Date object and serializes it via toLocalDateTimeString()
    // into a NAIVE "YYYY-MM-DDTHH:mm" string with no offset - typing "09:00"
    // always produces "...T09:00" regardless of the visitor's or the event's
    // timezone. Laravel parses that naive string using the app's default
    // timezone (config('app.timezone'), asserted below), and
    // resolveActivePhaseForPurchase()/isActiveAt() compare it against now(),
    // which resolves through that SAME default timezone. Both the write path
    // (form -> Eloquent cast) and the read path (purchase-time now()) agree on
    // one timezone, so a "09:00-10:00" phase window means the same wall-clock
    // hour on both ends - there is no stored-as-UTC-but-compared-as-local (or
    // vice versa) bug to fix here.
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(date_default_timezone_get())->toBe('Asia/Jakarta');

    Carbon::setTestNow(Carbon::parse('2026-07-15 09:30:00', 'Asia/Jakarta'));

    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => null]);

    // A naive string exactly as the admin form would submit it for a phase
    // meant to be active right now.
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 10000,
        'starts_at' => '2026-07-15 09:00:00',
        'ends_at' => '2026-07-15 10:00:00',
    ]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect((float) $order->items()->first()->unit_price)->toBe(10000.0);

    Carbon::setTestNow();
});
