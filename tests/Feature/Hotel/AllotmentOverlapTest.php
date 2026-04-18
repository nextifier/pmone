<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['hotels.read', 'allotments.create', 'allotments.read', 'allotments.update'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->for($this->event)->create();
    $this->roomType = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);
});

test('cannot create overlapping allotment for same room type', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'quantity' => 10,
    ]);

    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/allotments", [
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-15',
        'end_date' => '2026-07-15',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);
});

test('can create non-overlapping allotment for same room type', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'quantity' => 10,
    ]);

    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/allotments", [
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
    ]);

    $response->assertStatus(201);
});

test('update allotment excludes self from overlap check', function () {
    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'quantity' => 10,
    ]);

    $response = $this->putJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/allotments/{$allotment->id}", [
        'quantity' => 15,
    ]);

    $response->assertSuccessful();
});

test('update allotment detects overlap with other allotments', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'quantity' => 10,
    ]);
    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'quantity' => 10,
    ]);

    $response = $this->putJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/allotments/{$allotment->id}", [
        'start_date' => '2026-06-15',
        'end_date' => '2026-07-31',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);
});
