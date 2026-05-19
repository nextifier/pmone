<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
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

    $this->eventA = Event::factory()->create();
    $this->eventB = Event::factory()->create();
});

test('attaching an existing hotel to an event creates a pivot row', function () {
    $hotel = Hotel::factory()->create();

    $response = $this->postJson("/api/events/{$this->eventA->id}/hotels", [
        'hotel_id' => $hotel->id,
        'pivot' => [
            'is_active' => true,
            'notes' => 'Preferred partner',
        ],
    ]);

    $response->assertStatus(201)->assertJsonPath('data.slug', $hotel->slug);
    $this->assertDatabaseHas('hotel_event', [
        'hotel_id' => $hotel->id,
        'event_id' => $this->eventA->id,
        'is_active' => true,
        'notes' => 'Preferred partner',
    ]);
});

test('attaching the same hotel twice is idempotent', function () {
    $hotel = Hotel::factory()->withEvent($this->eventA)->create();

    $response = $this->postJson("/api/events/{$this->eventA->id}/hotels", [
        'hotel_id' => $hotel->id,
    ]);

    $response->assertStatus(201);
    expect(
        HotelEvent::where(['hotel_id' => $hotel->id, 'event_id' => $this->eventA->id])->count()
    )->toBe(1);
});

test('detach removes pivot but keeps hotel and other event pivots intact', function () {
    $hotel = Hotel::factory()->create();
    $hotel->events()->syncWithoutDetaching([
        $this->eventA->id => ['is_active' => true],
        $this->eventB->id => ['is_active' => true],
    ]);

    $response = $this->deleteJson("/api/events/{$this->eventA->id}/hotels/{$hotel->slug}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('hotel_event', [
        'hotel_id' => $hotel->id,
        'event_id' => $this->eventA->id,
    ]);
    $this->assertDatabaseHas('hotel_event', [
        'hotel_id' => $hotel->id,
        'event_id' => $this->eventB->id,
    ]);
    $this->assertDatabaseHas('hotels', [
        'id' => $hotel->id,
        'deleted_at' => null,
    ]);
});

test('event listing only returns hotels attached via pivot', function () {
    $attachedHotel = Hotel::factory()->withEvent($this->eventA)->create();
    Hotel::factory()->withEvent($this->eventB)->create();

    $response = $this->getJson("/api/events/{$this->eventA->id}/hotels");

    $response->assertSuccessful();
    $slugs = collect($response->json('data'))->pluck('slug');
    expect($slugs)->toContain($attachedHotel->slug)->toHaveCount(1);
});

test('soft deleting the hotel globally cascades pivot rows away from event listings', function () {
    $hotel = Hotel::factory()->withEvent($this->eventA)->create();

    $this->deleteJson("/api/hotels/{$hotel->slug}")->assertSuccessful();

    $response = $this->getJson("/api/events/{$this->eventA->id}/hotels");
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(0);
});

test('global slug uniqueness is enforced across events', function () {
    Hotel::factory()->create(['name' => 'Conflict Hotel', 'slug' => 'conflict-hotel']);

    $response = $this->postJson('/api/hotels', [
        'name' => 'Different Hotel',
        'slug' => 'conflict-hotel',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['slug']);
});
