<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
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
        'start_date' => '2026-04-11 00:00:00',
        'end_date' => '2026-04-13 23:59:59',
    ]);
    $this->addOn = Ticket::factory()->addOn()->create(['event_id' => $this->event->id]);
    $this->entry = Ticket::factory()->create(['event_id' => $this->event->id]);
});

it('accepts a session within the event date range', function () {
    $this->postJson("/api/events/{$this->event->id}/tickets/{$this->addOn->slug}/sessions", [
        'label' => '12 Apr 12:00',
        'starts_at' => '2026-04-12 12:00:00',
        'ends_at' => '2026-04-12 12:15:00',
    ])->assertCreated();
});

it('rejects a session outside the event date range', function () {
    $this->postJson("/api/events/{$this->event->id}/tickets/{$this->addOn->slug}/sessions", [
        'label' => 'Too early',
        'starts_at' => '2026-04-01 12:00:00',
        'ends_at' => '2026-04-01 12:15:00',
    ])->assertStatus(422)->assertJsonValidationErrors('starts_at');
});

it('rejects sessions on an entry ticket', function () {
    $this->postJson("/api/events/{$this->event->id}/tickets/{$this->entry->slug}/sessions", [
        'label' => 'Invalid',
        'starts_at' => '2026-04-12 12:00:00',
        'ends_at' => '2026-04-12 12:15:00',
    ])->assertStatus(422)->assertJsonValidationErrors('label');
});
