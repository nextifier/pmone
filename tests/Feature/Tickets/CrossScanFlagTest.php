<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->eventA = Event::factory()->create(['project_id' => $this->project->id]);
    $this->eventB = Event::factory()->create(['project_id' => $this->project->id]);

    // Link the two events bidirectionally (mirrors EventConjunctionController::store).
    $this->eventA->conjunctionEvents()->attach($this->eventB->id, ['order_column' => 1]);
    $this->eventB->conjunctionEvents()->attach($this->eventA->id, ['order_column' => 1]);
});

it('toggles allow_cross_scan set-wide across the conjunction clique', function () {
    $url = "/api/projects/{$this->project->username}/events/{$this->eventA->slug}/conjunctions/cross-scan";

    $this->putJson($url, ['allow_cross_scan' => true])
        ->assertSuccessful()
        ->assertJsonPath('allow_cross_scan', true);

    // Both directions of the pivot carry the flag (SQLite stores bools as 1/0).
    expect((bool) DB::table('event_conjunctions')->where('event_id', $this->eventA->id)->value('allow_cross_scan'))->toBeTrue();
    expect((bool) DB::table('event_conjunctions')->where('event_id', $this->eventB->id)->value('allow_cross_scan'))->toBeTrue();
});

it('exposes the cross-scan state in the conjunction index', function () {
    DB::table('event_conjunctions')->update(['allow_cross_scan' => true]);

    $this->getJson("/api/projects/{$this->project->username}/events/{$this->eventA->slug}/conjunctions")
        ->assertSuccessful()
        ->assertJsonPath('allow_cross_scan', true)
        ->assertJsonPath('data.0.allow_cross_scan', true);
});
