<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    $this->base = "/api/events/{$this->event->id}/tickets/{$this->ticket->slug}/price-phases";
});

it('rejects a price phase that overlaps an existing one', function () {
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id,
        'starts_at' => '2026-04-01 00:00:00',
        'ends_at' => '2026-04-10 23:59:59',
    ]);

    $this->postJson($this->base, [
        'label' => 'Overlap',
        'price' => 50000,
        'starts_at' => '2026-04-05 00:00:00',
        'ends_at' => '2026-04-15 23:59:59',
    ])->assertStatus(422)->assertJsonValidationErrors('starts_at');
});

it('accepts an adjacent non-overlapping price phase', function () {
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id,
        'starts_at' => '2026-04-01 00:00:00',
        'ends_at' => '2026-04-10 23:59:59',
    ]);

    $this->postJson($this->base, [
        'label' => 'Normal',
        'price' => 60000,
        'starts_at' => '2026-04-11 00:00:00',
        'ends_at' => '2026-04-20 23:59:59',
    ])->assertCreated();
});

it('excludes the phase itself when updating', function () {
    $phase = TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id,
        'starts_at' => '2026-04-01 00:00:00',
        'ends_at' => '2026-04-10 23:59:59',
    ]);

    $this->putJson("{$this->base}/{$phase->id}", [
        'label' => 'Renamed',
        'price' => 70000,
        'starts_at' => '2026-04-01 00:00:00',
        'ends_at' => '2026-04-10 23:59:59',
    ])->assertSuccessful();
});

it('treats null bounds as open-ended and detects overlap against them', function () {
    // An open-ended existing phase (no start, no end) spans all time.
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $this->postJson($this->base, [
        'label' => 'Any',
        'price' => 10000,
        'starts_at' => '2026-04-05 00:00:00',
        'ends_at' => '2026-04-15 23:59:59',
    ])->assertStatus(422)->assertJsonValidationErrors('starts_at');
});
