<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = ['hotels.create', 'hotels.read', 'hotels.update', 'hotels.delete'];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->event = Event::factory()->create();
});

test('admin can list hotels', function () {
    Hotel::factory()->count(3)->for($this->event)->create();

    $response = $this->getJson("/api/events/{$this->event->id}/hotels");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBe(3);
});

test('admin can create a hotel', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels", [
        'name' => 'Grand Test Hotel',
        'city' => 'Jakarta',
        'commission_rate' => 12.5,
        'tax_percentage' => 11,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Grand Test Hotel')
        ->assertJsonPath('data.city', 'Jakarta')
        ->assertJsonPath('data.event_id', $this->event->id);

    $this->assertDatabaseHas('hotels', [
        'name' => 'Grand Test Hotel',
        'city' => 'Jakarta',
        'event_id' => $this->event->id,
    ]);
});

test('admin can show a hotel by slug', function () {
    $hotel = Hotel::factory()->for($this->event)->create();

    $response = $this->getJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $hotel->id)
        ->assertJsonPath('data.slug', $hotel->slug);
});

test('admin can update a hotel', function () {
    $hotel = Hotel::factory()->for($this->event)->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}", [
        'name' => 'New Name',
        'commission_rate' => 15,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('hotels', ['id' => $hotel->id, 'name' => 'New Name']);
});

test('admin can soft delete a hotel', function () {
    $hotel = Hotel::factory()->for($this->event)->create();

    $response = $this->deleteJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}");

    $response->assertSuccessful();

    $this->assertSoftDeleted('hotels', ['id' => $hotel->id]);
});

test('user without permission cannot create hotel', function () {
    $other = User::factory()->create(['email_verified_at' => now()]);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $other->assignRole('staff');

    $this->actingAs($other);

    $response = $this->postJson("/api/events/{$this->event->id}/hotels", ['name' => 'No Permission']);

    $response->assertForbidden();
});

test('hotel media collections are configured', function () {
    $hotel = Hotel::factory()->for($this->event)->create();
    $collections = $hotel->getMediaCollections();

    expect($collections)->toHaveKeys(['featured', 'gallery']);
    expect($collections['featured']['single_file'])->toBeTrue();
    expect($collections['gallery']['single_file'])->toBeFalse();
});
