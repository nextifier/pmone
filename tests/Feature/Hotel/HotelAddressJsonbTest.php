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

    $this->event = Event::factory()->create();
});

test('creating a hotel persists nested address as jsonb', function () {
    $response = $this->postJson("/api/events/{$this->event->id}/hotels", [
        'name' => 'JSONB Hotel',
        'address' => [
            'street' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'country' => 'Indonesia',
        ],
    ]);

    $response->assertStatus(201);

    $hotel = Hotel::where('name', 'JSONB Hotel')->first();
    expect($hotel->address)->toBeArray();
    expect($hotel->street)->toBe('Jl. Test No. 1');
    expect($hotel->city)->toBe('Jakarta');
    expect($hotel->province)->toBe('DKI Jakarta');
    expect($hotel->country)->toBe('Indonesia');
});

test('updating a hotel replaces the address jsonb', function () {
    $hotel = Hotel::factory()->withEvent($this->event)->create();

    $response = $this->putJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}", [
        'address' => [
            'street' => 'Jl. Baru No. 9',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'country' => 'Indonesia',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.city', 'Bandung')
        ->assertJsonPath('data.province', 'Jawa Barat');

    $hotel->refresh();
    expect($hotel->city)->toBe('Bandung');
    expect($hotel->street)->toBe('Jl. Baru No. 9');
});

test('hotel resource exposes flat address fields from jsonb', function () {
    $hotel = Hotel::factory()->withEvent($this->event)->create([
        'address' => [
            'street' => 'Jl. Flat No. 5',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'country' => 'Indonesia',
        ],
    ]);

    $this->getJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.address', 'Jl. Flat No. 5')
        ->assertJsonPath('data.city', 'Jakarta')
        ->assertJsonPath('data.province', 'DKI Jakarta')
        ->assertJsonPath('data.country', 'Indonesia');
});

test('global hotel index filters hotels by city stored in jsonb', function () {
    Hotel::factory()->create(['address' => ['city' => 'Jakarta', 'country' => 'Indonesia']]);
    Hotel::factory()->create(['address' => ['city' => 'Bandung', 'country' => 'Indonesia']]);

    $response = $this->getJson('/api/hotels?filter_city=Jakarta');

    $response->assertSuccessful();
    $cities = collect($response->json('data'))->pluck('city')->unique()->values()->all();
    expect($cities)->toBe(['Jakarta']);
});

test('global hotel index sorts hotels by city stored in jsonb', function () {
    Hotel::factory()->create(['name' => 'Zeta Hotel', 'address' => ['city' => 'Yogyakarta']]);
    Hotel::factory()->create(['name' => 'Alpha Hotel', 'address' => ['city' => 'Bandung']]);

    $response = $this->getJson('/api/hotels?sort=city');

    $response->assertSuccessful();
    $cities = collect($response->json('data'))->pluck('city')->all();
    expect($cities)->toBe(['Bandung', 'Yogyakarta']);
});

test('global hotel search matches city inside jsonb', function () {
    Hotel::factory()->create(['name' => 'Faraway Inn', 'address' => ['city' => 'Surabaya']]);
    Hotel::factory()->create(['name' => 'Other Inn', 'address' => ['city' => 'Medan']]);

    $response = $this->getJson('/api/hotels?filter_search=suraba');

    $response->assertSuccessful();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Faraway Inn');
    expect($names)->not->toContain('Other Inn');
});
