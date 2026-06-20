<?php

use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
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
    $this->base = "/api/events/{$this->event->id}/tickets";
});

it('accepts valid_days that belong to the same event', function () {
    $days = EventDay::factory()->count(2)->sequence(
        ['day_number' => 1, 'date' => '2026-04-11'],
        ['day_number' => 2, 'date' => '2026-04-12'],
    )->create(['event_id' => $this->event->id]);

    $this->postJson($this->base, [
        'kind' => 'entry',
        'title' => ['en' => 'Three-Day Pass'],
        'purchase_type' => 'first_party',
        'valid_days' => $days->pluck('id')->all(),
    ])->assertCreated();
});

it('rejects valid_days from another event', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $foreignDay = EventDay::factory()->create(['event_id' => $otherEvent->id]);

    $this->postJson($this->base, [
        'kind' => 'entry',
        'title' => ['en' => 'Bad Pass'],
        'purchase_type' => 'first_party',
        'valid_days' => [$foreignDay->id],
    ])->assertStatus(422)->assertJsonValidationErrors('valid_days');
});

it('rejects valid_days on an add-on ticket', function () {
    $day = EventDay::factory()->create(['event_id' => $this->event->id]);

    $this->postJson($this->base, [
        'kind' => 'add_on',
        'title' => ['en' => 'Workshop'],
        'purchase_type' => 'first_party',
        'valid_days' => [$day->id],
    ])->assertStatus(422)->assertJsonValidationErrors('valid_days');
});
