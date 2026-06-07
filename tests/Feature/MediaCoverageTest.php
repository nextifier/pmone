<?php

use App\Models\Event;
use App\Models\MediaCoverage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'media_coverages.create', 'media_coverages.read', 'media_coverages.update',
        'media_coverages.delete', 'media_coverages.restore',
        'events.read', 'events.update', 'projects.read',
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
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/media-coverages";
});

it('lists media coverages scoped to the event', function () {
    MediaCoverage::factory()->count(3)->create(['event_id' => $this->event->id]);

    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    MediaCoverage::factory()->count(2)->create(['event_id' => $otherEvent->id]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('creates a media coverage', function () {
    $payload = [
        'title' => 'Megabuild 2026 di PIK 2',
        'url' => 'https://www.antaranews.com/berita/123/megabuild',
        'published_at' => '2025-12-14T10:00:00',
    ];

    $response = $this->postJson($this->apiBase, $payload);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Megabuild 2026 di PIK 2')
        ->assertJsonPath('data.url', 'https://www.antaranews.com/berita/123/megabuild');

    $item = MediaCoverage::where('title', 'Megabuild 2026 di PIK 2')->first();
    expect($item)->not->toBeNull();
    expect($item->event_id)->toBe($this->event->id);
    expect($item->published_at->format('Y-m-d'))->toBe('2025-12-14');
});

it('requires title and url on store', function () {
    $this->postJson($this->apiBase, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'url']);
});

it('rejects an invalid url', function () {
    $this->postJson($this->apiBase, ['title' => 'X', 'url' => 'javascript:alert(1)'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

it('updates a media coverage', function () {
    $item = MediaCoverage::factory()->create(['event_id' => $this->event->id]);

    $response = $this->putJson("{$this->apiBase}/{$item->id}", [
        'title' => 'Updated Title',
        'is_active' => false,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.is_active', false);
});

it('soft-deletes and restores a media coverage', function () {
    $item = MediaCoverage::factory()->create(['event_id' => $this->event->id]);

    $this->deleteJson("{$this->apiBase}/{$item->id}")->assertSuccessful();
    expect($item->fresh()->trashed())->toBeTrue();

    $this->postJson("{$this->apiBase}/trash/{$item->id}/restore")->assertSuccessful();
    expect($item->fresh()->trashed())->toBeFalse();
});

it('reorders media coverages scoped to the event', function () {
    $a = MediaCoverage::factory()->create(['event_id' => $this->event->id]);
    $b = MediaCoverage::factory()->create(['event_id' => $this->event->id]);
    $c = MediaCoverage::factory()->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $a->id, 'order' => 3],
            ['id' => $b->id, 'order' => 1],
            ['id' => $c->id, 'order' => 2],
        ],
    ]);

    $response->assertSuccessful();
    expect($a->fresh()->order_column)->toBe(3);
    expect($b->fresh()->order_column)->toBe(1);
    expect($c->fresh()->order_column)->toBe(2);
});

it('rejects reorder when an id does not belong to the event', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $foreign = MediaCoverage::factory()->create(['event_id' => $otherEvent->id]);
    $own = MediaCoverage::factory()->create(['event_id' => $this->event->id]);

    $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $own->id, 'order' => 1],
            ['id' => $foreign->id, 'order' => 2],
        ],
    ])->assertStatus(422);
});

it('bulk updates is_active', function () {
    $items = MediaCoverage::factory()->count(3)->create(['event_id' => $this->event->id, 'is_active' => true]);

    $this->patchJson("{$this->apiBase}/bulk", [
        'ids' => $items->pluck('id')->all(),
        'is_active' => false,
    ])->assertSuccessful();

    expect(MediaCoverage::where('event_id', $this->event->id)->where('is_active', false)->count())->toBe(3);
});

it('lists source events and copies media coverage from another event', function () {
    $sourceEvent = Event::factory()->create(['project_id' => $this->project->id]);
    MediaCoverage::factory()->count(2)->create(['event_id' => $sourceEvent->id]);

    $this->getJson("{$this->apiBase}/source-events")
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $sourceEvent->id)
        ->assertJsonPath('data.0.media_coverages_count', 2);

    $this->postJson("{$this->apiBase}/copy-from-event", [
        'source_event_id' => $sourceEvent->id,
    ])->assertSuccessful();

    expect($this->event->mediaCoverages()->count())->toBe(2);
});

it('forbids users without the required permission', function () {
    $unauthorized = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($unauthorized);

    MediaCoverage::factory()->create(['event_id' => $this->event->id]);

    $this->getJson($this->apiBase)->assertStatus(403);
});
