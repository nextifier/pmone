<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
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

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
});

test('can create hotel with new detail fields', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels", [
        'name' => 'Luxury Resort',
        'star_rating' => 5,
        'category' => 'resort',
        'facilities' => ['Pool', 'Spa', 'Gym'],
        'google_maps_link' => 'https://maps.app.goo.gl/abcdef',
        'google_maps_embed_src' => 'https://www.google.com/maps/embed?pb=test',
        'website_url' => 'https://luxury-resort.com',
        'cancellation_policy' => 'Free cancellation up to 48 hours',
        'children_policy' => 'Children under 12 stay free',
        'nearest_airport' => 'DPS - Ngurah Rai',
        'airport_distance_km' => 15,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.star_rating', 5)
        ->assertJsonPath('data.category', 'resort')
        ->assertJsonPath('data.facilities', ['Pool', 'Spa', 'Gym'])
        ->assertJsonPath('data.website_url', 'https://luxury-resort.com')
        ->assertJsonPath('data.nearest_airport', 'DPS - Ngurah Rai')
        ->assertJsonPath('data.airport_distance_km', 15);

    $this->assertDatabaseHas('hotels', [
        'name' => 'Luxury Resort',
        'star_rating' => 5,
        'category' => 'resort',
    ]);
});

test('star_rating must be between 1 and 5', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels", [
        'name' => 'Invalid Rating',
        'star_rating' => 6,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('star_rating');
});

test('website_url must be valid url', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels", [
        'name' => 'Invalid URL',
        'website_url' => 'not-a-url',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('website_url');
});

test('facilities field is stored as array', function () {
    $hotel = Hotel::factory()->for($this->event)->create([
        'facilities' => ['WiFi', 'Parking'],
    ]);

    expect($hotel->fresh()->facilities)->toBe(['WiFi', 'Parking']);
});

test('public hotel endpoint exposes new fields', function () {
    $consumer = ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);

    $this->event->update(['is_active' => true]);

    $hotel = Hotel::factory()->for($this->event)->create([
        'star_rating' => 4,
        'category' => 'business',
        'facilities' => ['WiFi', 'Gym'],
        'google_maps_link' => 'https://maps.app.goo.gl/test',
        'nearest_airport' => 'CGK',
        'airport_distance_km' => 10,
    ]);

    $response = $this->getJson(
        "/api/public/events/{$this->event->slug}/hotels/{$hotel->slug}",
        ['X-API-Key' => $consumer->api_key],
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.star_rating', 4)
        ->assertJsonPath('data.category', 'business')
        ->assertJsonPath('data.facilities', ['WiFi', 'Gym'])
        ->assertJsonPath('data.nearest_airport', 'CGK')
        ->assertJsonPath('data.airport_distance_km', 10);
});
