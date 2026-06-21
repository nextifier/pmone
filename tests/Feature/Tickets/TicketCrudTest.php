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
    ]);
    $this->base = "/api/events/{$this->event->id}/tickets";
});

it('lists tickets scoped to the event', function () {
    Ticket::factory()->count(2)->create(['event_id' => $this->event->id]);
    $other = Event::factory()->create(['project_id' => $this->project->id]);
    Ticket::factory()->create(['event_id' => $other->id]);

    $this->getJson($this->base)
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('creates a ticket with a slug derived from the title', function () {
    $this->postJson($this->base, [
        'kind' => 'entry',
        'title' => ['en' => 'VIP Pass'],
        'tier' => 'VIP',
        'purchase_type' => 'first_party',
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'vip-pass')
        ->assertJsonPath('data.title.en', 'VIP Pass');

    expect(Ticket::where('event_id', $this->event->id)->count())->toBe(1);
});

it('persists and updates the day-pass and entrance badge labels', function () {
    $this->postJson($this->base, [
        'kind' => 'entry',
        'title' => ['en' => 'Day Pass'],
        'purchase_type' => 'first_party',
        'more_details' => ['day_pass' => 'All-day pass', 'entrance' => 'Regular entrance'],
    ])->assertCreated()
        ->assertJsonPath('data.more_details.day_pass', 'All-day pass')
        ->assertJsonPath('data.more_details.entrance', 'Regular entrance');

    $ticket = Ticket::where('event_id', $this->event->id)->firstOrFail();
    expect($ticket->more_details['day_pass'])->toBe('All-day pass')
        ->and($ticket->more_details['entrance'])->toBe('Regular entrance');

    $this->putJson("{$this->base}/{$ticket->slug}", [
        'kind' => 'entry',
        'title' => ['en' => 'Day Pass'],
        'purchase_type' => 'first_party',
        'more_details' => ['day_pass' => 'Two-day pass', 'entrance' => 'VIP entrance'],
    ])->assertSuccessful()
        ->assertJsonPath('data.more_details.day_pass', 'Two-day pass');

    expect($ticket->fresh()->more_details['entrance'])->toBe('VIP entrance');
});

it('scopes slug uniqueness to the event', function () {
    Ticket::factory()->create(['event_id' => $this->event->id, 'title' => ['en' => 'Regular'], 'slug' => 'regular']);

    $this->postJson($this->base, [
        'kind' => 'entry',
        'title' => ['en' => 'Regular'],
        'purchase_type' => 'first_party',
    ])->assertCreated()->assertJsonPath('data.slug', 'regular-1');
});

it('requires an external_url for external tickets', function () {
    $this->postJson($this->base, [
        'kind' => 'entry',
        'title' => ['en' => 'External'],
        'purchase_type' => 'external',
    ])->assertStatus(422)->assertJsonValidationErrors('external_url');
});

it('shows, updates and soft-deletes a ticket', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);

    $this->getJson("{$this->base}/{$ticket->slug}")->assertSuccessful()
        ->assertJsonPath('data.id', $ticket->id);

    $this->putJson("{$this->base}/{$ticket->slug}", [
        'kind' => 'entry',
        'title' => ['en' => 'Updated Title'],
        'purchase_type' => 'first_party',
        'is_active' => false,
    ])->assertSuccessful()->assertJsonPath('data.is_active', false);

    $this->deleteJson("{$this->base}/{$ticket->slug}")->assertSuccessful();

    expect(Ticket::find($ticket->id))->toBeNull();
    expect(Ticket::withTrashed()->find($ticket->id))->not->toBeNull();
});
