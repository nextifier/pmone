<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['hotels.read', 'hotels.create', 'hotels.update', 'hotels.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

test('hotel list under event returns only attached hotels via pivot', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    Hotel::factory()->count(2)->withEvent($eventA)->create();
    Hotel::factory()->count(3)->withEvent($eventB)->create();

    $responseA = $this->getJson("/api/events/{$eventA->id}/hotels");
    $responseB = $this->getJson("/api/events/{$eventB->id}/hotels");

    expect($responseA->json('meta.total'))->toBe(2);
    expect($responseB->json('meta.total'))->toBe(3);
});

test('hotel not attached to event returns 404 via event scope', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($eventA)->create();

    $response = $this->getJson("/api/events/{$eventB->id}/hotels/{$hotel->slug}");

    $response->assertNotFound();
});

test('hotel slug auto-increments when duplicate name is created', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $first = Hotel::factory()->withEvent($eventA)->create(['name' => 'Grand Mercure Globally Unique']);
    $second = Hotel::factory()->withEvent($eventB)->create(['name' => 'Grand Mercure Globally Unique']);

    // HasSlug trait auto-dedupes via suffix (slug is globally unique now)
    expect($first->slug)->toBe('grand-mercure-globally-unique');
    expect($second->slug)->not->toBe('grand-mercure-globally-unique');
    expect($second->slug)->toStartWith('grand-mercure-globally-unique');
});

test('shared hotel attached to multiple events appears in each event listing', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $shared = Hotel::factory()->create();
    $shared->events()->attach([$eventA->id => ['is_active' => true], $eventB->id => ['is_active' => true]]);

    expect($this->getJson("/api/events/{$eventA->id}/hotels")->json('meta.total'))->toBe(1);
    expect($this->getJson("/api/events/{$eventB->id}/hotels")->json('meta.total'))->toBe(1);
});

test('nested event hotel store creates global hotel + pivot row', function () {
    $event = Event::factory()->create();

    $response = $this->postJson("/api/events/{$event->id}/hotels", [
        'name' => 'Nested Hotel',
        'city' => 'Bandung',
    ]);

    $response->assertStatus(201);
    $hotel = Hotel::where('name', 'Nested Hotel')->first();
    expect($hotel)->not->toBeNull();
    $this->assertDatabaseHas('hotel_event', ['hotel_id' => $hotel->id, 'event_id' => $event->id]);
});

test('nested event hotel store attaches existing hotel when hotel_id provided', function () {
    $event = Event::factory()->create();
    $existing = Hotel::factory()->create();

    $response = $this->postJson("/api/events/{$event->id}/hotels", [
        'hotel_id' => $existing->id,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('hotel_event', ['hotel_id' => $existing->id, 'event_id' => $event->id]);
});

test('detaching hotel from event keeps hotel record alive', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();

    $response = $this->deleteJson("/api/events/{$event->id}/hotels/{$hotel->slug}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('hotel_event', ['hotel_id' => $hotel->id, 'event_id' => $event->id]);
    $this->assertDatabaseHas('hotels', ['id' => $hotel->id, 'deleted_at' => null]);
});
