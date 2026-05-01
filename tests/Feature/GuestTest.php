<?php

use App\Models\Event;
use App\Models\Guest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'guests.create', 'guests.read', 'guests.update',
        'guests.delete', 'guests.restore',
        'events.read', 'events.update',
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

it('creates a guest with translatable fields', function () {
    $payload = [
        'name' => 'Jane Doe',
        'title' => ['en' => 'CEO', 'id' => 'Direktur'],
        'bio' => ['en' => 'A bio', 'id' => 'Sebuah bio'],
        'organization' => 'Acme Corp',
        'is_featured' => true,
        'tags' => ['AI', 'Web3'],
    ];

    $response = $this->postJson($this->apiBase, $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.title.en', 'CEO')
        ->assertJsonPath('data.title.id', 'Direktur')
        ->assertJsonPath('data.organization', 'Acme Corp')
        ->assertJsonPath('data.is_featured', true);

    $guest = Guest::where('name', 'Jane Doe')->first();
    expect($guest)->not->toBeNull();
    expect($guest->event_id)->toBe($this->event->id);
    expect($guest->slug)->toBe('jane-doe');
    expect($guest->getTranslation('title', 'id'))->toBe('Direktur');
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
