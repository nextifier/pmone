<?php

use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'programs.create', 'programs.read', 'programs.update',
        'programs.delete', 'programs.restore',
        'events.read', 'events.update',
        'projects.read',
    ];

    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/programs";
});

it('lists programs scoped to the event ordered by order_column', function () {
    Program::factory()->count(3)->create(['event_id' => $this->event->id]);

    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    Program::factory()->count(2)->create(['event_id' => $otherEvent->id]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('creates a program with translatable title and description', function () {
    $payload = [
        'title' => ['en' => 'Exhibition', 'id' => 'Pameran'],
        'description' => ['en' => 'The main expo', 'id' => 'Pameran utama'],
        'icon' => 'hugeicons:mic-01',
        'is_active' => true,
    ];

    $response = $this->postJson($this->apiBase, $payload);

    $response->assertCreated()
        ->assertJsonPath('data.title.en', 'Exhibition')
        ->assertJsonPath('data.title.id', 'Pameran')
        ->assertJsonPath('data.icon', 'hugeicons:mic-01')
        ->assertJsonPath('data.is_active', true);

    $program = Program::first();
    expect($program)->not->toBeNull();
    expect($program->event_id)->toBe($this->event->id);
    expect($program->getTranslation('title', 'id'))->toBe('Pameran');
    expect($program->order_column)->not->toBeNull();
});

it('requires an english title', function () {
    $response = $this->postJson($this->apiBase, [
        'title' => ['id' => 'Tanpa English'],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('title.en');
});

it('updates a program preserving untouched locales', function () {
    $program = Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Old', 'id' => 'Lama', 'ja' => 'ジャ'],
    ]);

    $response = $this->putJson("{$this->apiBase}/{$program->id}", [
        'title' => ['en' => 'New', 'id' => 'Baru', 'ja' => 'ジャ'],
        'icon' => 'hugeicons:agreement-01',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.title.en', 'New')
        ->assertJsonPath('data.icon', 'hugeicons:agreement-01');

    expect($program->fresh()->getTranslation('title', 'ja'))->toBe('ジャ');
});

it('soft deletes a program', function () {
    $program = Program::factory()->create(['event_id' => $this->event->id]);

    $this->deleteJson("{$this->apiBase}/{$program->id}")->assertSuccessful();

    expect(Program::find($program->id))->toBeNull();
    expect(Program::withTrashed()->find($program->id))->not->toBeNull();
});

it('reorders programs', function () {
    $a = Program::factory()->create(['event_id' => $this->event->id]);
    $b = Program::factory()->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $b->id, 'order' => 1],
            ['id' => $a->id, 'order' => 2],
        ],
    ]);

    $response->assertSuccessful();
    expect($b->fresh()->order_column)->toBe(1);
    expect($a->fresh()->order_column)->toBe(2);
});

it('rejects reorder of programs from another event', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $foreign = Program::factory()->create(['event_id' => $otherEvent->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [['id' => $foreign->id, 'order' => 1]],
    ]);

    $response->assertStatus(422);
});

it('restores and force deletes from trash', function () {
    $program = Program::factory()->create(['event_id' => $this->event->id]);
    $program->delete();

    $this->getJson("{$this->apiBase}/trash")->assertSuccessful()->assertJsonCount(1, 'data');

    $this->postJson("{$this->apiBase}/trash/{$program->id}/restore")->assertSuccessful();
    expect(Program::find($program->id))->not->toBeNull();

    $program->delete();
    $this->deleteJson("{$this->apiBase}/trash/{$program->id}")->assertSuccessful();
    expect(Program::withTrashed()->find($program->id))->toBeNull();
});

it('forbids creating a program without permission', function () {
    $this->user->removeRole('master');
    $this->user->syncPermissions(['events.read']);

    $response = $this->postJson($this->apiBase, [
        'title' => ['en' => 'Nope'],
    ]);

    $response->assertForbidden();
});
