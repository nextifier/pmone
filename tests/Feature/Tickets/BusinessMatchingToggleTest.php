<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\FieldResponse;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
});

function bmEvent(Project $project, bool $bmEnabled): Event
{
    return Event::factory()->create([
        'project_id' => $project->id,
        'tickets_enabled' => true,
        'business_matching_enabled' => $bmEnabled,
    ]);
}

it('hides checkout custom fields when business matching is disabled', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_bm_off']);
    $event = bmEvent($this->project, false);
    EventCustomField::factory()->create(['event_id' => $event->id, 'is_active' => true]);

    $this->withHeaders(['X-API-Key' => 'pk_bm_off'])
        ->getJson("/api/public/events/{$event->slug}/custom-fields")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('exposes checkout custom fields when business matching is enabled', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_bm_on']);
    $event = bmEvent($this->project, true);
    EventCustomField::factory()->create(['event_id' => $event->id, 'is_active' => true]);

    $this->withHeaders(['X-API-Key' => 'pk_bm_on'])
        ->getJson("/api/public/events/{$event->slug}/custom-fields")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('ignores business matching answers when the program is disabled', function () {
    $event = bmEvent($this->project, false);
    $field = EventCustomField::factory()->create(['event_id' => $event->id, 'is_active' => true]);
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    app(TicketPurchaseService::class)->createOrder([
        'event_id' => $event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'business_matching' => ['opt_in' => true, 'responses' => [['custom_field_id' => $field->id, 'value' => 'Acme']]],
    ]);

    expect(FieldResponse::count())->toBe(0);
});

it('stores business matching answers when the program is enabled', function () {
    $event = bmEvent($this->project, true);
    $field = EventCustomField::factory()->create(['event_id' => $event->id, 'is_active' => true]);
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    app(TicketPurchaseService::class)->createOrder([
        'event_id' => $event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
        'business_matching' => ['opt_in' => true, 'responses' => [['custom_field_id' => $field->id, 'value' => 'Acme']]],
    ]);

    expect(FieldResponse::count())->toBe(1);
});

it('persists the business matching toggle via ticket settings', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $event = bmEvent($this->project, false);

    $this->actingAs($admin)
        ->putJson("/api/events/{$event->id}/ticket-settings", ['business_matching_enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.business_matching_enabled', true);

    expect($event->fresh()->business_matching_enabled)->toBeTrue();
});
