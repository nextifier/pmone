<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_scalper']);
    $this->headers = ['X-API-Key' => 'pk_test_scalper'];

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function capTicket(Event $event, float $price = 0, ?int $stock = null, array $overrides = []): Ticket
{
    $ticket = Ticket::factory()->create(array_merge([
        'event_id' => $event->id,
        'stock' => $stock,
        'max_quantity' => null,
        'max_per_buyer' => null,
    ], $overrides));

    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

// ─── Per-buyer purchase cap ──────────────────────────────────────────────────

it('rejects a second order that would push the buyer over a ticket-level max_per_buyer', function () {
    $ticket = capTicket($this->event, 0, overrides: ['max_per_buyer' => 3]);

    $first = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'scalper-ticket@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    expect($first->status)->toBe(TicketOrderStatus::Confirmed);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'scalper-ticket@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::where('buyer_email', 'scalper-ticket@example.com')->count())->toBe(1)
        ->and($ticket->fresh()->sold_count)->toBe(2);
});

it('rejects a second order that would push the buyer over the event-level max_tickets_per_buyer', function () {
    $this->event->update(['max_tickets_per_buyer' => 3]);
    $ticketA = capTicket($this->event, 0);
    $ticketB = capTicket($this->event, 0);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'scalper-event@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticketA->id, 'quantity' => 2]],
    ]);

    // Even a DIFFERENT ticket in the same event is blocked - the event-wide
    // cap aggregates across every ticket type.
    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'scalper-event@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticketB->id, 'quantity' => 2]],
    ]))->toThrow(HttpException::class);

    expect(TicketOrder::where('buyer_email', 'scalper-event@example.com')->count())->toBe(1);
});

it('does not restrict a buyer when max_per_buyer and max_tickets_per_buyer are both null', function () {
    $ticket = capTicket($this->event, 0);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'uncapped@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 10]],
    ]);
    $second = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'uncapped@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 10]],
    ]);

    expect($second)->not->toBeNull()
        ->and(TicketOrder::where('buyer_email', 'uncapped@example.com')->count())->toBe(2);
});

it('counts a still-pending (non-expired) order toward the per-buyer cap', function () {
    $ticket = capTicket($this->event, 100000, overrides: ['max_per_buyer' => 2]);

    $pending = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'pending@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    expect($pending->status)->toBe(TicketOrderStatus::PendingPayment);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'pending@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);
});

it('excludes a pending order whose payment window has lapsed from the held quantity', function () {
    $ticket = capTicket($this->event, 100000, overrides: ['max_per_buyer' => 2]);

    $lapsed = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'lapsed@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    $lapsed->update(['payment_expires_at' => now()->subMinute()]);

    $second = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'lapsed@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    expect($second)->not->toBeNull();
});

it('excludes cancelled and expired orders from the held quantity', function () {
    $ticket = capTicket($this->event, 0, overrides: ['max_per_buyer' => 2]);

    $cancelled = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'excluded@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    $cancelled->update(['status' => TicketOrderStatus::Cancelled]);

    $expired = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'excluded@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    $expired->update(['status' => TicketOrderStatus::Expired]);

    $allowed = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'excluded@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    expect($allowed)->not->toBeNull()
        ->and(TicketOrder::where('buyer_email', 'excluded@example.com')->count())->toBe(3);
});

// ─── Server-side Turnstile verification ──────────────────────────────────────

it('rejects a missing turnstile token on a bot-protected event when a secret is configured', function () {
    config(['turnstile.secret' => 'test-secret']);
    Http::fake();
    $this->event->update(['bot_protection_enabled' => true]);
    $ticket = capTicket($this->event, 0);

    $this->withHeaders($this->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => $this->event->id,
        'buyer_name' => 'Budi', 'buyer_email' => 'budi1@example.com', 'buyer_phone' => '08123',
        'accept_terms' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('turnstile_token');

    Http::assertNothingSent();
    expect(TicketOrder::where('buyer_email', 'budi1@example.com')->count())->toBe(0);
});

it('rejects an invalid turnstile token on a bot-protected event', function () {
    config(['turnstile.secret' => 'test-secret']);
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
    ]);
    $this->event->update(['bot_protection_enabled' => true]);
    $ticket = capTicket($this->event, 0);

    $this->withHeaders($this->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => $this->event->id,
        'buyer_name' => 'Budi', 'buyer_email' => 'budi2@example.com', 'buyer_phone' => '08123',
        'accept_terms' => true,
        'turnstile_token' => 'bad-token',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('turnstile_token');

    expect(TicketOrder::where('buyer_email', 'budi2@example.com')->count())->toBe(0);
});

it('accepts a valid turnstile token on a bot-protected event', function () {
    config(['turnstile.secret' => 'test-secret']);
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);
    $this->event->update(['bot_protection_enabled' => true]);
    $ticket = capTicket($this->event, 0);

    $this->withHeaders($this->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => $this->event->id,
        'buyer_name' => 'Budi', 'buyer_email' => 'budi3@example.com', 'buyer_phone' => '08123',
        'accept_terms' => true,
        'turnstile_token' => 'good-token',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ])->assertCreated();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'challenges.cloudflare.com/turnstile/v0/siteverify'));
    expect(TicketOrder::where('buyer_email', 'budi3@example.com')->count())->toBe(1);
});

it('ignores turnstile_token entirely when the event is not bot-protected', function () {
    config(['turnstile.secret' => 'test-secret']);
    Http::fake();
    // bot_protection_enabled defaults to false.
    $ticket = capTicket($this->event, 0);

    $this->withHeaders($this->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => $this->event->id,
        'buyer_name' => 'Budi', 'buyer_email' => 'budi4@example.com', 'buyer_phone' => '08123',
        'accept_terms' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ])->assertCreated();

    Http::assertNothingSent();
});

it('fails open when bot protection is enabled but no turnstile secret is configured', function () {
    config(['turnstile.secret' => null]);
    Http::fake();
    $this->event->update(['bot_protection_enabled' => true]);
    $ticket = capTicket($this->event, 0);

    $this->withHeaders($this->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => $this->event->id,
        'buyer_name' => 'Budi', 'buyer_email' => 'budi5@example.com', 'buyer_phone' => '08123',
        'accept_terms' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ])->assertCreated();

    Http::assertNothingSent();
});

it('cannot be bypassed by a client-supplied source=admin body field', function () {
    // Security guard: the public proxy forwards the browser body verbatim, so
    // a `source=admin` field is attacker-controllable and must NOT skip bot
    // verification. It is neither accepted as input nor honored as a bypass.
    config(['turnstile.secret' => 'test-secret']);
    Http::fake(); // fail if anything actually calls siteverify
    $this->event->update(['bot_protection_enabled' => true]);
    $ticket = capTicket($this->event, 0);

    $this->withHeaders($this->headers)->postJson('/api/public/ticket-orders', [
        'event_id' => $this->event->id,
        'buyer_name' => 'Scalper', 'buyer_email' => 'scalper@example.com', 'buyer_phone' => '08123',
        'accept_terms' => true,
        'source' => 'admin', // spoofed - must be ignored
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('turnstile_token');

    // The spoofed source did not leak into a created order either.
    expect(TicketOrder::where('buyer_email', 'scalper@example.com')->exists())->toBeFalse();
});
