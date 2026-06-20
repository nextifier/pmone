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
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->base = "/api/events/{$this->event->id}/tickets";
});

it('forbids a user without ticket permissions', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user'); // no ticket perms
    $this->actingAs($user);

    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, [
        'kind' => 'entry',
        'title' => ['en' => 'Nope'],
        'purchase_type' => 'first_party',
    ])->assertForbidden();
});

it('allows staff to manage tickets', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('staff');
    $this->actingAs($user);

    $this->getJson($this->base)->assertSuccessful();
});

it('returns 404 when tickets are disabled for the event', function () {
    $disabledEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => false,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');
    $this->actingAs($user);

    Ticket::factory()->create(['event_id' => $disabledEvent->id]);

    $this->getJson("/api/events/{$disabledEvent->id}/tickets")
        ->assertNotFound()
        ->assertJsonPath('error_code', 'TICKETS_DISABLED');
});
