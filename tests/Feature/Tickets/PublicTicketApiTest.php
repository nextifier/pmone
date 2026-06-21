<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_tickets']);
    $this->headers = ['X-API-Key' => 'pk_test_tickets'];

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
});

function onSaleTicket(Event $event, float $price = 0): Ticket
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket;
}

it('lists on-sale tickets for an event website', function () {
    onSaleTicket($this->event, 60000);
    Ticket::factory()->external()->create(['event_id' => $this->event->id]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/tickets")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.on_sale', true);
});

it('exposes the sales window for the sale countdown', function () {
    onSaleTicket($this->event, 60000);

    $upcoming = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $upcoming->id,
        'label' => 'Pre-sale',
        'price' => 50000,
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeeks(2),
    ]);

    $data = collect($this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/tickets")
        ->assertSuccessful()
        ->json('data'))
        ->keyBy('id');

    expect($data[$upcoming->id]['sales_status'])->toBe('upcoming')
        ->and($data[$upcoming->id]['sales_starts_at'])->not->toBeNull()
        ->and($data[$upcoming->id]['sales_phase_label'])->toBe('Pre-sale');

    $onSale = $data->first(fn ($t) => $t['sales_status'] === 'on_sale');
    expect($onSale['sales_ends_at'])->not->toBeNull();
});

it('previews the upcoming phase price and exposes badge fields', function () {
    $upcoming = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'more_details' => ['day_pass' => 'All-day pass', 'entrance' => 'VIP entrance'],
    ]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $upcoming->id,
        'price' => 75000,
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeeks(2),
    ]);

    $closed = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $closed->id,
        'price' => 40000,
        'starts_at' => now()->subWeeks(2),
        'ends_at' => now()->subWeek(),
    ]);

    $data = collect($this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/tickets")
        ->assertSuccessful()
        ->json('data'))
        ->keyBy('id');

    // Upcoming: not on sale, no live price, but a muted preview from the next phase.
    expect($data[$upcoming->id]['on_sale'])->toBeFalse()
        ->and($data[$upcoming->id]['price'])->toBeNull()
        ->and((float) $data[$upcoming->id]['display_price'])->toBe(75000.0)
        ->and($data[$upcoming->id]['day_pass'])->toBe('All-day pass')
        ->and($data[$upcoming->id]['entrance'])->toBe('VIP entrance');

    // Closed: sales ended, so no preview price.
    expect($data[$closed->id]['sales_status'])->toBe('closed')
        ->and($data[$closed->id]['display_price'])->toBeNull();
});

it('mirrors the live price into display_price while on sale', function () {
    $live = onSaleTicket($this->event, 60000);

    $data = collect($this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/tickets")
        ->assertSuccessful()
        ->json('data'))
        ->keyBy('id');

    expect((float) $data[$live->id]['display_price'])->toBe(60000.0);
});

it('returns 404 listing when tickets are disabled', function () {
    $this->event->update(['tickets_enabled' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/events/{$this->event->slug}/tickets")
        ->assertNotFound()
        ->assertJsonPath('error_code', 'TICKETS_DISABLED');
});

it('previews a cart subtotal', function () {
    $ticket = onSaleTicket($this->event, 50000);

    $this->withHeaders($this->headers)
        ->postJson('/api/public/tickets/preview', [
            'event_id' => $this->event->id,
            'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.subtotal', 100000);
});

it('answers the email lookup without leaking PII', function () {
    User::factory()->create(['email' => 'known@example.com']);

    $this->withHeaders($this->headers)
        ->postJson('/api/public/tickets/email-lookup', ['email' => 'known@example.com'])
        ->assertSuccessful()
        ->assertJsonPath('data.exists', true)
        ->assertJsonMissingPath('data.name');

    $this->withHeaders($this->headers)
        ->postJson('/api/public/tickets/email-lookup', ['email' => 'nobody@example.com'])
        ->assertJsonPath('data.exists', false);
});

it('claims a free order end-to-end via the public API', function () {
    $ticket = onSaleTicket($this->event, 0);

    $response = $this->withHeaders($this->headers)
        ->postJson('/api/public/ticket-orders', [
            'event_id' => $this->event->id,
            'buyer_name' => 'Budi',
            'buyer_email' => 'budi@example.com',
            'buyer_phone' => '08123',
            'accept_terms' => true,
            'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.is_free', true);

    $ulid = $response->json('data.ulid');
    expect(TicketOrder::where('ulid', $ulid)->exists())->toBeTrue();

    $confirmed = $this->withHeaders($this->headers)
        ->getJson("/api/public/ticket-orders/{$ulid}")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.attendees');

    // A confirmed (free) order reveals each attendee's gate-scanner key.
    expect($confirmed->json('data.attendees.0.qr_token'))->not->toBeNull();
});

it('completes checkout when the buyer email belongs to a soft-deleted account', function () {
    $ticket = onSaleTicket($this->event, 0);

    $deleted = User::factory()->create(['email' => 'activerow@example.com']);
    $deleted->delete();

    $this->withHeaders($this->headers)
        ->postJson('/api/public/ticket-orders', [
            'event_id' => $this->event->id,
            'buyer_name' => 'Anton',
            'buyer_email' => 'activerow@example.com',
            'buyer_phone' => '+6281212341234',
            'accept_terms' => true,
            'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed');

    expect(User::withTrashed()->where('email', 'activerow@example.com')->count())->toBe(1)
        ->and($deleted->fresh()->trashed())->toBeFalse();
});

it('withholds qr_token for a pending (unpaid) order', function () {
    // A paid ticket with no configured gateway keeps the order pending, yet
    // attendees + tokens already exist. The token must stay hidden until paid.
    $ticket = onSaleTicket($this->event, 60000);

    $response = $this->withHeaders($this->headers)
        ->postJson('/api/public/ticket-orders', [
            'event_id' => $this->event->id,
            'buyer_name' => 'Budi',
            'buyer_email' => 'budi@example.com',
            'buyer_phone' => '08123',
            'accept_terms' => true,
            'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.attendees.0.qr_token', null);

    // The status page (show) withholds it too.
    $ulid = $response->json('data.ulid');
    $this->withHeaders($this->headers)
        ->getJson("/api/public/ticket-orders/{$ulid}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.attendees')
        ->assertJsonPath('data.attendees.0.qr_token', null);

    // The standalone attendee endpoint withholds it, and the QR image 404s.
    $attendeeUlid = TicketOrder::where('ulid', $ulid)->first()->attendees()->first()->ulid;
    $this->withHeaders($this->headers)
        ->getJson("/api/public/attendees/{$attendeeUlid}")
        ->assertSuccessful()
        ->assertJsonPath('data.qr_token', null)
        ->assertJsonPath('order.is_confirmed', false);
    $this->get("/api/public/attendees/{$attendeeUlid}/qr.png")->assertNotFound();
});

it('requires accepting the terms', function () {
    $ticket = onSaleTicket($this->event, 0);

    $this->withHeaders($this->headers)
        ->postJson('/api/public/ticket-orders', [
            'event_id' => $this->event->id,
            'buyer_name' => 'Budi',
            'buyer_email' => 'budi@example.com',
            'buyer_phone' => '08123',
            'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('accept_terms');
});
