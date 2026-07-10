<?php

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['brands.create', 'brands.read', 'brands.update', 'brands.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

test('updating a brand persists nested address as jsonb', function () {
    $brand = Brand::factory()->create(['address' => null]);

    $response = $this->putJson("/api/brands/{$brand->slug}", [
        'address' => [
            'street' => 'Jl. Test No. 1',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'country' => 'Indonesia',
        ],
    ]);

    $response->assertSuccessful();

    $brand->refresh();
    expect($brand->address)->toBeArray();
    expect($brand->address['street'])->toBe('Jl. Test No. 1');
    expect($brand->address['city'])->toBe('Jakarta Selatan');
    expect($brand->address['province'])->toBe('DKI Jakarta');
    expect($brand->address['country'])->toBe('Indonesia');
});

test('brand show exposes the address object', function () {
    $brand = Brand::factory()->create([
        'address' => [
            'street' => 'Jl. Flat No. 5',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'country' => 'Indonesia',
        ],
    ]);

    $this->getJson("/api/brands/{$brand->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.address.city', 'Bandung')
        ->assertJsonPath('data.address.street', 'Jl. Flat No. 5');
});

test('sending a null address clears the column', function () {
    $brand = Brand::factory()->create([
        'address' => ['street' => 'Jl. Lama', 'city' => '', 'province' => '', 'country' => 'Indonesia'],
    ]);

    $this->putJson("/api/brands/{$brand->slug}", ['address' => null])
        ->assertSuccessful();

    expect($brand->refresh()->address)->toBeNull();
});

test('a street longer than 1000 characters is rejected', function () {
    $brand = Brand::factory()->create(['address' => null]);

    $this->putJson("/api/brands/{$brand->slug}", [
        'address' => ['street' => str_repeat('a', 1001)],
    ])->assertStatus(422)->assertJsonValidationErrors('address.street');
});

test('the brands index filters by country stored in jsonb', function () {
    Brand::factory()->create([
        'name' => 'Local Brand',
        'address' => ['street' => '', 'city' => '', 'province' => '', 'country' => 'Indonesia'],
    ]);
    Brand::factory()->create([
        'name' => 'Foreign Brand',
        'address' => ['street' => '', 'city' => '', 'province' => '', 'country' => 'Singapore'],
    ]);

    $response = $this->getJson('/api/brands?filter_country=Indonesia')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.brand_name'))->toBe('Local Brand');
})->skip(env('DB_CONNECTION', 'sqlite') === 'sqlite', 'ilike requires PostgreSQL');

test('filter options return the distinct address facets', function () {
    Brand::factory()->create([
        'address' => ['street' => '', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'country' => 'Indonesia'],
    ]);
    Brand::factory()->create([
        'address' => ['street' => '', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'country' => 'Indonesia'],
    ]);
    Brand::factory()->create([
        'address' => ['street' => '', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'country' => 'Indonesia'],
    ]);

    $response = $this->getJson('/api/brands/filter-options')->assertSuccessful();

    expect($response->json('countries'))->toBe(['Indonesia']);
    expect($response->json('provinces'))->toBe(['DKI Jakarta', 'Jawa Barat']);
    expect($response->json('cities'))->toBe(['Bandung', 'Jakarta']);
});

test('filter options only expose locations of brands the exhibitor can see', function () {
    $exhibitorRole = Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);
    $exhibitorRole->syncPermissions(['brands.read', 'brands.update']);

    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $ownBrand = Brand::factory()->create([
        'address' => ['street' => '', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'country' => 'Indonesia'],
    ]);
    $ownBrand->users()->attach($exhibitor->id);

    Brand::factory()->create([
        'address' => ['street' => '', 'city' => 'Singapore', 'province' => '', 'country' => 'Singapore'],
    ]);

    $response = $this->actingAs($exhibitor)
        ->getJson('/api/brands/filter-options')
        ->assertSuccessful();

    expect($response->json('countries'))->toBe(['Indonesia']);
    expect($response->json('cities'))->toBe(['Jakarta']);
});
