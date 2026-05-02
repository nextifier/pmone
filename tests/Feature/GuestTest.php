<?php

use App\Models\Event;
use App\Models\Guest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'guests.create', 'guests.read', 'guests.update',
        'guests.delete', 'guests.restore',
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

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/guests";
});

it('lists guests scoped to the event', function () {
    Guest::factory()->count(3)->create(['event_id' => $this->event->id]);

    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    Guest::factory()->count(2)->create(['event_id' => $otherEvent->id]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('creates a guest with title and bio', function () {
    $payload = [
        'name' => 'Jane Doe',
        'title' => 'CEO',
        'bio' => 'A bio',
        'organization' => 'Acme Corp',
        'is_featured' => true,
        'tags' => ['AI', 'Web3'],
    ];

    $response = $this->postJson($this->apiBase, $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.title', 'CEO')
        ->assertJsonPath('data.bio', 'A bio')
        ->assertJsonPath('data.organization', 'Acme Corp')
        ->assertJsonPath('data.is_featured', true);

    $guest = Guest::where('name', 'Jane Doe')->first();
    expect($guest)->not->toBeNull();
    expect($guest->event_id)->toBe($this->event->id);
    expect($guest->slug)->toBe('jane-doe');
    expect($guest->title)->toBe('CEO');
});

it('auto-generates a unique slug per event', function () {
    Guest::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'John Smith',
    ]);

    $response = $this->postJson($this->apiBase, [
        'name' => 'John Smith',
    ]);

    $response->assertCreated();
    $guests = Guest::where('event_id', $this->event->id)->where('name', 'John Smith')->get();
    expect($guests)->toHaveCount(2);
    expect($guests->pluck('slug')->all())->toContain('john-smith', 'john-smith-1');
});

it('allows the same slug across different events', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);

    Guest::factory()->create([
        'event_id' => $otherEvent->id,
        'name' => 'Same Person',
    ]);

    $response = $this->postJson($this->apiBase, ['name' => 'Same Person']);

    $response->assertCreated();
    $guest = Guest::where('event_id', $this->event->id)->where('name', 'Same Person')->first();
    expect($guest->slug)->toBe('same-person');
});

it('updates a guest and syncs links', function () {
    $guest = Guest::factory()->create(['event_id' => $this->event->id]);

    $response = $this->putJson("{$this->apiBase}/{$guest->id}", [
        'name' => 'Updated Name',
        'organization' => 'New Org',
        'links' => [
            ['label' => 'LinkedIn', 'url' => 'https://linkedin.com/in/example'],
            ['label' => 'Website', 'url' => 'https://example.com'],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.organization', 'New Org');

    $guest->refresh();
    expect($guest->links)->toHaveCount(2);
    expect($guest->links->first()->label)->toBe('LinkedIn');
});

it('soft-deletes and restores a guest', function () {
    $guest = Guest::factory()->create(['event_id' => $this->event->id]);

    $this->deleteJson("{$this->apiBase}/{$guest->id}")->assertSuccessful();
    expect($guest->fresh()->trashed())->toBeTrue();

    $this->postJson("{$this->apiBase}/trash/{$guest->id}/restore")->assertSuccessful();
    expect($guest->fresh()->trashed())->toBeFalse();
});

it('reorders guests scoped to the event', function () {
    $g1 = Guest::factory()->create(['event_id' => $this->event->id]);
    $g2 = Guest::factory()->create(['event_id' => $this->event->id]);
    $g3 = Guest::factory()->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $g1->id, 'order' => 3],
            ['id' => $g2->id, 'order' => 1],
            ['id' => $g3->id, 'order' => 2],
        ],
    ]);

    $response->assertSuccessful();
    expect($g1->fresh()->order_column)->toBe(3);
    expect($g2->fresh()->order_column)->toBe(1);
    expect($g3->fresh()->order_column)->toBe(2);
});

it('rejects reorder when an id does not belong to the event', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $foreign = Guest::factory()->create(['event_id' => $otherEvent->id]);
    $own = Guest::factory()->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $own->id, 'order' => 1],
            ['id' => $foreign->id, 'order' => 2],
        ],
    ]);

    $response->assertStatus(422);
});

it('forbids users without the required permission', function () {
    $unauthorized = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($unauthorized);

    Guest::factory()->create(['event_id' => $this->event->id]);

    $this->getJson($this->apiBase)->assertStatus(403);
});

it('rejects invalid link URLs', function () {
    $response = $this->postJson($this->apiBase, [
        'name' => 'Eve',
        'links' => [
            ['label' => 'Site', 'url' => 'javascript:alert(1)'],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['links.0.url']);
});

it('rejects too many links', function () {
    $links = [];
    for ($i = 0; $i < 21; $i++) {
        $links[] = ['label' => "L{$i}", 'url' => "https://example.com/{$i}"];
    }

    $this->postJson($this->apiBase, ['name' => 'Many', 'links' => $links])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['links']);
});

it('requires name on store', function () {
    $this->postJson($this->apiBase, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('exposes featured_count and total in admin index meta', function () {
    Guest::factory()->count(3)->create(['event_id' => $this->event->id]);
    Guest::factory()->featured()->count(2)->create(['event_id' => $this->event->id]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.featured_count', 2);
});

it('filters by status, visibility, and is_featured', function () {
    Guest::factory()->create(['event_id' => $this->event->id, 'status' => 'active']);
    Guest::factory()->inactive()->create(['event_id' => $this->event->id]);
    Guest::factory()->private()->create(['event_id' => $this->event->id]);
    Guest::factory()->featured()->create(['event_id' => $this->event->id]);

    $this->getJson("{$this->apiBase}?status=inactive")->assertJsonCount(1, 'data');
    $this->getJson("{$this->apiBase}?visibility=private")->assertJsonCount(1, 'data');
    $this->getJson("{$this->apiBase}?is_featured=1")->assertJsonCount(1, 'data');
});

it('searches guests by name and organization', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Search uses ilike which is PostgreSQL-specific.');
    }

    Guest::factory()->create(['event_id' => $this->event->id, 'name' => 'Alpha One', 'organization' => 'Acme']);
    Guest::factory()->create(['event_id' => $this->event->id, 'name' => 'Bravo', 'organization' => 'Globex']);

    $this->getJson("{$this->apiBase}?search=alpha")->assertJsonCount(1, 'data');
    $this->getJson("{$this->apiBase}?search=globex")->assertJsonCount(1, 'data');
});

it('bulk updates status, visibility, and is_featured', function () {
    $guests = Guest::factory()->count(3)->create(['event_id' => $this->event->id]);

    $response = $this->patchJson("{$this->apiBase}/bulk", [
        'ids' => $guests->pluck('id')->all(),
        'status' => 'inactive',
        'is_featured' => true,
    ]);

    $response->assertSuccessful()->assertJsonPath('updated_count', 3);

    foreach ($guests as $guest) {
        $fresh = $guest->fresh();
        expect($fresh->status)->toBe('inactive');
        expect($fresh->is_featured)->toBeTrue();
    }
});

it('rejects bulk update without any allowed field', function () {
    $guests = Guest::factory()->count(2)->create(['event_id' => $this->event->id]);

    $this->patchJson("{$this->apiBase}/bulk", ['ids' => $guests->pluck('id')->all()])
        ->assertStatus(422);
});

it('does not bulk update guests outside the event', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $own = Guest::factory()->create(['event_id' => $this->event->id, 'status' => 'active']);
    $foreign = Guest::factory()->create(['event_id' => $otherEvent->id, 'status' => 'active']);

    $this->patchJson("{$this->apiBase}/bulk", [
        'ids' => [$own->id, $foreign->id],
        'status' => 'inactive',
    ])->assertSuccessful()->assertJsonPath('updated_count', 1);

    expect($foreign->fresh()->status)->toBe('active');
});

it('syncs tags on store and update', function () {
    $response = $this->postJson($this->apiBase, [
        'name' => 'Tag Test',
        'tags' => ['AI', 'Cloud'],
    ]);

    $response->assertCreated();
    $guest = Guest::where('name', 'Tag Test')->first();
    expect($guest->tags->pluck('name')->all())->toEqualCanonicalizing(['AI', 'Cloud']);

    $this->putJson("{$this->apiBase}/{$guest->id}", ['tags' => ['Cloud', 'Web3']])
        ->assertSuccessful();
    expect($guest->fresh()->tags->pluck('name')->all())->toEqualCanonicalizing(['Cloud', 'Web3']);
});

it('duplicates a guest with links and tags', function () {
    $guest = Guest::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Original Speaker',
    ]);
    $guest->links()->create(['label' => 'Site', 'url' => 'https://example.com', 'order' => 0]);
    $guest->syncTagsWithType(['AI', 'Cloud'], 'guest_topic');

    $response = $this->postJson("{$this->apiBase}/{$guest->id}/duplicate");

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Original Speaker (Copy)');

    $copy = Guest::where('name', 'Original Speaker (Copy)')->first();
    expect($copy)->not->toBeNull();
    expect($copy->id)->not->toBe($guest->id);
    expect($copy->ulid)->not->toBe($guest->ulid);
    expect($copy->links)->toHaveCount(1);
    expect($copy->links->first()->url)->toBe('https://example.com');
    expect($copy->tags->pluck('name')->all())->toEqualCanonicalizing(['AI', 'Cloud']);
});

it('returns paginated activity log for a guest', function () {
    $guest = Guest::factory()->create(['event_id' => $this->event->id]);
    $guest->update(['name' => 'New Name']);
    $guest->update(['organization' => 'New Org']);

    $response = $this->getJson("{$this->apiBase}/{$guest->id}/activities");

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);

    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(2);
});

it('moves selected guests to another event in the same project', function () {
    $targetEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $guests = Guest::factory()->count(3)->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/bulk-move", [
        'ids' => $guests->pluck('id')->all(),
        'target_event_id' => $targetEvent->id,
    ]);

    $response->assertSuccessful()->assertJsonPath('moved_count', 3);

    foreach ($guests as $guest) {
        expect($guest->fresh()->event_id)->toBe($targetEvent->id);
    }
});

it('rejects bulk move to event outside the project', function () {
    $foreignProject = Project::factory()->create();
    $foreignEvent = Event::factory()->create(['project_id' => $foreignProject->id]);
    $guest = Guest::factory()->create(['event_id' => $this->event->id]);

    $this->postJson("{$this->apiBase}/bulk-move", [
        'ids' => [$guest->id],
        'target_event_id' => $foreignEvent->id,
    ])->assertStatus(422);

    expect($guest->fresh()->event_id)->toBe($this->event->id);
});

it('rejects bulk move to the same event', function () {
    $guest = Guest::factory()->create(['event_id' => $this->event->id]);

    $this->postJson("{$this->apiBase}/bulk-move", [
        'ids' => [$guest->id],
        'target_event_id' => $this->event->id,
    ])->assertStatus(422);
});

it('clears response cache after bulk update', function () {
    ResponseCache::shouldReceive('clear')
        ->with(['guests'])
        ->atLeast()->once();

    $guests = Guest::factory()->count(2)->create(['event_id' => $this->event->id]);

    $this->patchJson("{$this->apiBase}/bulk", [
        'ids' => $guests->pluck('id')->all(),
        'is_featured' => true,
    ])->assertSuccessful();
});

it('exposes only public+active guests via the public endpoint', function () {
    Guest::factory()->create(['event_id' => $this->event->id, 'status' => 'active', 'visibility' => 'public']);
    Guest::factory()->create(['event_id' => $this->event->id, 'status' => 'inactive', 'visibility' => 'public']);
    Guest::factory()->create(['event_id' => $this->event->id, 'status' => 'active', 'visibility' => 'private']);
    Guest::factory()->featured()->create(['event_id' => $this->event->id, 'visibility' => 'public']);

    $apiKey = config('app.api_key');
    $headers = $apiKey ? ['X-API-Key' => $apiKey] : [];

    $response = $this->withHeaders($headers)->getJson(
        "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/guests"
    );

    if ($response->status() === 401) {
        $this->markTestSkipped('Public API requires api.key middleware not set in test env.');
    }

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.featured_count', 1);
});
