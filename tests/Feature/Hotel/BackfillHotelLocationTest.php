<?php

use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('backfill-location sets the correct kota and province by slug', function () {
    $hotel = Hotel::factory()->create([
        'name' => 'Fairmont Jakarta',
        'slug' => 'fairmont-jakarta',
        'address' => ['street' => 'Jl. Asia Afrika', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'country' => 'Indonesia'],
    ]);

    $this->artisan('hotels:backfill-location')->assertSuccessful();

    $hotel->refresh();
    expect($hotel->city)->toBe('Kota Jakarta Pusat');
    expect($hotel->province)->toBe('DKI Jakarta');
    // Other address keys are preserved.
    expect($hotel->street)->toBe('Jl. Asia Afrika');
    expect($hotel->country)->toBe('Indonesia');
});

test('backfill-location corrects a Tangerang hotel to its kota', function () {
    $hotel = Hotel::factory()->create([
        'name' => 'The Grove Suites by Grand Aston BSD City',
        'slug' => 'the-grove-suites-bsd-city',
        'address' => ['city' => 'Tangerang', 'province' => 'Banten', 'country' => 'Indonesia'],
    ]);

    $this->artisan('hotels:backfill-location')->assertSuccessful();

    expect($hotel->fresh()->city)->toBe('Kota Tangerang Selatan');
});

test('backfill-location dry run does not persist changes', function () {
    $hotel = Hotel::factory()->create([
        'name' => 'Fairmont Jakarta',
        'slug' => 'fairmont-jakarta',
        'address' => ['city' => 'Jakarta', 'province' => 'DKI Jakarta'],
    ]);

    $this->artisan('hotels:backfill-location', ['--dry-run' => true])->assertSuccessful();

    expect($hotel->fresh()->city)->toBe('Jakarta');
});

test('backfill-location leaves non-seeded hotels untouched', function () {
    $hotel = Hotel::factory()->create([
        'name' => 'Some Random Hotel',
        'slug' => 'some-random-hotel',
        'address' => ['city' => 'Jakarta'],
    ]);

    $this->artisan('hotels:backfill-location')->assertSuccessful();

    expect($hotel->fresh()->city)->toBe('Jakarta');
});
