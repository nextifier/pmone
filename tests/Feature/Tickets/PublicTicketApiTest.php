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

    $this->withHeaders($this->headers)
        ->getJson("/api/public/ticket-orders/{$ulid}")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.attendees');
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
