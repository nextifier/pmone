<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\RoomTypePricingPeriod;
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
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
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
    $otherHotel = Hotel::factory()->withEvent($this->event)->create();
    $room = RoomType::factory()->create(['hotel_id' => $otherHotel->id]);

    $response = $this->getJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types/{$room->slug}");

    $response->assertNotFound();
});

test('room type slug is auto-appended when name duplicates', function () {
    RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'name' => 'Deluxe', 'slug' => 'deluxe']);

    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types", [
        'name' => 'Deluxe',
        'max_pax' => 2,
        'base_rate' => 1000000,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.slug', fn (string $slug) => str_starts_with($slug, 'deluxe-'));
});

test('admin can create room type with dynamic pricing periods', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types", [
        'name' => 'Dynamic King',
        'max_pax' => 2,
        'base_rate' => 1500000,
        'pricing_type' => 'dynamic',
        'pricing_periods' => [
            ['start_date' => '2026-06-01', 'end_date' => '2026-06-05', 'rate' => 1500000, 'label' => 'Weekday'],
            ['start_date' => '2026-06-06', 'end_date' => '2026-06-07', 'rate' => 1800000, 'label' => 'Weekend'],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.pricing_type', 'dynamic');
    expect((float) $response->json('data.pricing_periods.0.rate'))->toBe(1500000.0);
    expect((float) $response->json('data.pricing_periods.1.rate'))->toBe(1800000.0);

    $this->assertDatabaseCount('room_type_pricing_periods', 2);
});

test('admin can update room type pricing periods (add, edit, remove)', function () {
    $room = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'pricing_type' => 'dynamic',
    ]);
    $existing = RoomTypePricingPeriod::factory()->for($room)->create([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
        'rate' => 1500000,
    ]);
    $toRemove = RoomTypePricingPeriod::factory()->for($room)->create([
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-05',
        'rate' => 2000000,
    ]);

    $response = $this->putJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types/{$room->slug}", [
        'pricing_type' => 'dynamic',
        'pricing_periods' => [
            ['id' => $existing->id, 'start_date' => '2026-06-01', 'end_date' => '2026-06-05', 'rate' => 1700000],
            ['start_date' => '2026-08-01', 'end_date' => '2026-08-03', 'rate' => 2500000],
        ],
    ]);

    $response->assertSuccessful();
    expect($room->fresh()->pricingPeriods()->count())->toBe(2);
    $this->assertSoftDeleted('room_type_pricing_periods', ['id' => $toRemove->id]);
    expect((float) $existing->fresh()->rate)->toBe(1700000.0);
});

test('validation rejects dynamic pricing with empty periods', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types", [
        'name' => 'Dyn Empty',
        'max_pax' => 2,
        'base_rate' => 1000000,
        'pricing_type' => 'dynamic',
        'pricing_periods' => [],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors'))->toHaveKey('pricing_periods');
});

test('validation rejects overlapping pricing periods', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types", [
        'name' => 'Overlap',
        'max_pax' => 2,
        'base_rate' => 1000000,
        'pricing_type' => 'dynamic',
        'pricing_periods' => [
            ['start_date' => '2026-06-01', 'end_date' => '2026-06-05', 'rate' => 1500000],
            ['start_date' => '2026-06-04', 'end_date' => '2026-06-08', 'rate' => 1800000],
        ],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors'))->toHaveKey('pricing_periods');
});

test('validation rejects end_date before start_date in period', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels/{$this->hotel->slug}/room-types", [
        'name' => 'Reverse',
        'max_pax' => 2,
        'base_rate' => 1000000,
        'pricing_type' => 'dynamic',
        'pricing_periods' => [
            ['start_date' => '2026-06-10', 'end_date' => '2026-06-05', 'rate' => 1500000],
        ],
    ]);

    $response->assertStatus(422);
});
