<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'hotels.read',
        'allotments.create', 'allotments.read', 'allotments.update', 'allotments.delete',
    ];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
    $this->hotel = Hotel::factory()->create();
    $this->roomType = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);
});

test('admin can list allotments for a hotel', function () {
    HotelEventAllotment::factory()->count(2)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'room_type_id' => $this->roomType->id,
    ]);

    $response = $this->getJson("/api/hotels/{$this->hotel->slug}/allotments");

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(2);
});

test('admin can create an allotment', function () {
    $response = $this->postJson("/api/hotels/{$this->hotel->slug}/allotments", [
        'event_id' => $this->event->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 20,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.quantity', 20);

    $this->assertDatabaseHas('hotel_event_allotments', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'quantity' => 20,
    ]);
});

test('allotment requires end_date >= start_date', function () {
    $response = $this->postJson("/api/hotels/{$this->hotel->slug}/allotments", [
        'event_id' => $this->event->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-05',
    ]);

    $response->assertStatus(422);
});

test('admin can update an allotment', function () {
    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 10,
    ]);

    $response = $this->putJson("/api/hotels/{$this->hotel->slug}/allotments/{$allotment->id}", [
        'quantity' => 50,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.quantity', 50);
});

test('admin can soft delete an allotment', function () {
    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'room_type_id' => $this->roomType->id,
    ]);

    $response = $this->deleteJson("/api/hotels/{$this->hotel->slug}/allotments/{$allotment->id}");

    $response->assertSuccessful();
    $this->assertSoftDeleted('hotel_event_allotments', ['id' => $allotment->id]);
});
