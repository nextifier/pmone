<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'hotels.read', 'hotels.update',
        'room_types.create', 'room_types.read', 'room_types.update', 'room_types.delete',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->for($this->event)->create();
});

test('admin can list room types for a hotel', function () {
    RoomType::factory()->count(2)->create(['hotel_id' => $this->hotel->id]);

    $response = $this->getJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types");

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(2);
});

test('admin can create a room type', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types", [
        'name' => 'Deluxe King',
        'max_pax' => 2,
        'base_rate' => 1500000,
        'breakfast_included' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Deluxe King');

    expect((float) $response->json('data.base_rate'))->toEqual(1500000.0);

    $this->assertDatabaseHas('room_types', [
        'hotel_id' => $this->hotel->id,
        'name' => 'Deluxe King',
    ]);
});

test('admin can show a room type', function () {
    $room = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);

    $response = $this->getJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types/{$room->slug}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $room->id);
});

test('admin can update a room type', function () {
    $room = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_rate' => 1000000]);

    $response = $this->putJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types/{$room->slug}", [
        'base_rate' => 2000000,
    ]);

    $response->assertSuccessful();
    expect((float) $response->json('data.base_rate'))->toEqual(2000000.0);
});

test('admin can soft delete a room type', function () {
    $room = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);

    $response = $this->deleteJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types/{$room->slug}");

    $response->assertSuccessful();
    $this->assertSoftDeleted('room_types', ['id' => $room->id]);
});

test('room type from another hotel returns 404', function () {
    $otherHotel = Hotel::factory()->for($this->event)->create();
    $room = RoomType::factory()->create(['hotel_id' => $otherHotel->id]);

    $response = $this->getJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types/{$room->slug}");

    $response->assertNotFound();
});

test('room type slug is unique per hotel', function () {
    RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'name' => 'Deluxe', 'slug' => 'deluxe']);

    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types", [
        'name' => 'Deluxe',
        'slug' => 'deluxe',
        'max_pax' => 2,
        'base_rate' => 1000000,
    ]);

    $response->assertStatus(422);
});
