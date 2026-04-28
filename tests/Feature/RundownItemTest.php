<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\RundownItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'rundown_items.create', 'rundown_items.read', 'rundown_items.update',
        'rundown_items.delete', 'rundown_items.restore',
        'events.read',
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
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/rundown-items";
});

it('returns rundown items grouped by date with day_number anchored at event start_date', function () {
    RundownItem::factory()->onDate('2026-07-23')->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Day 2 Item', 'id' => 'Item Hari 2'],
    ]);
    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Day 1 Item', 'id' => 'Item Hari 1'],
    ]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.days')
        ->assertJsonPath('data.days.0.date', '2026-07-22')
        ->assertJsonPath('data.days.0.day_number', 1)
        ->assertJsonPath('data.days.0.day_label', 'Day 1')
        ->assertJsonPath('data.days.1.date', '2026-07-23')
        ->assertJsonPath('data.days.1.day_number', 2);
});

it('emits empty days for the full event range even when no items exist', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-08-01 09:00:00',
        'end_date' => '2026-08-04 18:00:00',
    ]);

    RundownItem::factory()->onDate('2026-08-02')->create([
        'event_id' => $event->id,
        'title' => ['en' => 'Only Item'],
    ]);

    $response = $this->getJson("/api/projects/{$this->project->username}/events/{$event->slug}/rundown-items");

    $response->assertSuccessful()
        ->assertJsonCount(4, 'data.days')
        ->assertJsonPath('data.days.0.day_number', 1)
        ->assertJsonPath('data.days.0.items', [])
        ->assertJsonPath('data.days.1.day_number', 2)
        ->assertJsonPath('data.days.2.items', [])
        ->assertJsonPath('data.days.3.items', []);
});

it('groups null-date items as unscheduled', function () {
    RundownItem::factory()->create([
        'event_id' => $this->event->id,
        'date' => null,
    ]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful();

    $unscheduled = collect($response->json('data.days'))
        ->firstWhere('date', null);

    expect($unscheduled)->not->toBeNull()
        ->and($unscheduled['day_label'])->toBe('Unscheduled')
        ->and(count($unscheduled['items']))->toBe(1);
});

it('creates a rundown item with translatable fields and speakers', function () {
    $payload = [
        'date' => '2026-07-22',
        'start_time' => '09:00',
        'end_time' => '10:30',
        'title' => ['en' => 'Opening Keynote', 'id' => 'Keynote Pembukaan'],
        'description' => ['en' => 'Welcome speech', 'id' => 'Sambutan pembukaan'],
        'location' => ['en' => 'Main Hall', 'id' => 'Hall Utama'],
        'speakers' => [
            ['name' => 'Dr. John', 'title' => 'CEO', 'organization' => 'ABC'],
            ['name' => 'Jane Smith', 'title' => 'CTO', 'organization' => 'XYZ'],
        ],
        'categories' => ['Keynote', 'Opening'],
    ];

    $response = $this->postJson($this->apiBase, $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.title.en', 'Opening Keynote')
        ->assertJsonPath('data.title.id', 'Keynote Pembukaan')
        ->assertJsonPath('data.start_time', '09:00')
        ->assertJsonPath('data.end_time', '10:30')
        ->assertJsonCount(2, 'data.speakers')
        ->assertJsonCount(2, 'data.categories')
        ->assertJsonMissingPath('data.type');

    expect(RundownItem::query()
        ->where('event_id', $this->event->id)
        ->whereDate('date', '2026-07-22')
        ->exists()
    )->toBeTrue();
});

it('rejects dates outside the event range', function () {
    $response = $this->postJson($this->apiBase, [
        'date' => '2026-07-30',
        'title' => ['en' => 'Out of range'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['date']);
});

it('validates required title.en on create', function () {
    $response = $this->postJson($this->apiBase, [
        'date' => '2026-07-22',
        'title' => ['id' => 'Hanya Indonesia'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title.en']);
});

it('rejects end_time earlier than start_time', function () {
    $response = $this->postJson($this->apiBase, [
        'title' => ['en' => 'Bad'],
        'start_time' => '10:00',
        'end_time' => '09:00',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_time']);
});

it('updates a rundown item without losing other locale translations', function () {
    $item = RundownItem::factory()->create([
        'event_id' => $this->event->id,
        'date' => '2026-07-22',
        'title' => ['en' => 'Old EN', 'id' => 'Old ID'],
    ]);

    $response = $this->putJson("{$this->apiBase}/{$item->id}", [
        'title' => ['en' => 'New EN'],
    ]);

    $response->assertSuccessful();

    $item->refresh();
    expect($item->getTranslation('title', 'en'))->toBe('New EN');
    expect($item->getTranslation('title', 'id'))->toBe('Old ID');
});

it('soft deletes and restores a rundown item', function () {
    $item = RundownItem::factory()->create([
        'event_id' => $this->event->id,
        'date' => '2026-07-22',
    ]);

    $this->deleteJson("{$this->apiBase}/{$item->id}")->assertSuccessful();
    $this->assertSoftDeleted('rundown_items', ['id' => $item->id]);

    $this->postJson("{$this->apiBase}/trash/{$item->id}/restore")->assertSuccessful();
    $this->assertDatabaseHas('rundown_items', ['id' => $item->id, 'deleted_at' => null]);
});

it('lists trashed items separately', function () {
    $kept = RundownItem::factory()->create(['event_id' => $this->event->id, 'date' => '2026-07-22']);
    $deleted = RundownItem::factory()->create(['event_id' => $this->event->id, 'date' => '2026-07-22']);
    $deleted->delete();

    $indexResponse = $this->getJson($this->apiBase);
    $trashResponse = $this->getJson("{$this->apiBase}/trash");

    $indexItemIds = collect($indexResponse->json('data.days'))
        ->flatMap(fn ($day) => collect($day['items'])->pluck('id'))
        ->all();
    expect($indexItemIds)->toContain($kept->id)->not->toContain($deleted->id);

    $trashItemIds = collect($trashResponse->json('data'))->pluck('id')->all();
    expect($trashItemIds)->toContain($deleted->id)->not->toContain($kept->id);
});

it('reorders items within a date scope', function () {
    $a = RundownItem::factory()->onDate('2026-07-22')->create(['event_id' => $this->event->id]);
    $b = RundownItem::factory()->onDate('2026-07-22')->create(['event_id' => $this->event->id]);
    $c = RundownItem::factory()->onDate('2026-07-22')->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'date' => '2026-07-22',
        'orders' => [
            ['id' => $c->id, 'order' => 1],
            ['id' => $a->id, 'order' => 2],
            ['id' => $b->id, 'order' => 3],
        ],
    ]);

    $response->assertSuccessful();
    expect($a->fresh()->order_column)->toBe(2);
    expect($c->fresh()->order_column)->toBe(1);
});

it('scopes items to the requested event', function () {
    $otherEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);
    $otherItem = RundownItem::factory()->onDate('2026-07-22')->create(['event_id' => $otherEvent->id]);

    $response = $this->getJson($this->apiBase);

    $itemIds = collect($response->json('data.days'))
        ->flatMap(fn ($day) => collect($day['items'])->pluck('id'))
        ->all();

    expect($itemIds)->not->toContain($otherItem->id);
});

it('rejects reorder with empty orders array', function () {
    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['orders']);
});

it('rejects reorder with duplicate ids', function () {
    $a = RundownItem::factory()->onDate('2026-07-22')->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $a->id, 'order' => 1],
            ['id' => $a->id, 'order' => 2],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['orders.1.id']);
});

it('rejects reorder when ids belong to a different event', function () {
    $otherEvent = Event::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);
    $foreign = RundownItem::factory()->onDate('2026-07-22')->create(['event_id' => $otherEvent->id]);
    $own = RundownItem::factory()->onDate('2026-07-22')->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $own->id, 'order' => 1],
            ['id' => $foreign->id, 'order' => 2],
        ],
    ]);

    $response->assertStatus(422);
    expect($foreign->fresh()->order_column)->not->toBe(2);
});

it('blocks non-member users from managing the rundown', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($outsider);

    $response = $this->postJson($this->apiBase, [
        'date' => '2026-07-22',
        'title' => ['en' => 'Should fail'],
    ]);

    $response->assertForbidden();
});

it('strips legacy avatar_url from speakers payload', function () {
    $item = RundownItem::factory()->create([
        'event_id' => $this->event->id,
        'date' => '2026-07-22',
        'speakers' => [
            ['name' => 'Speaker', 'avatar_url' => 'https://example.com/a.jpg'],
        ],
    ]);

    $response = $this->getJson("{$this->apiBase}/{$item->id}");

    $response->assertSuccessful();
    expect($response->json('data.speakers.0.name'))->toBe('Speaker');
    expect($response->json('data.speakers.0'))->not->toHaveKey('avatar_url');
});
