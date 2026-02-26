<?php

use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'event_products.create', 'event_products.read', 'event_products.update', 'event_products.delete',
        'events.create', 'events.read', 'events.update', 'events.delete',
    ];

    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create();
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/products";
});

it('can list event products', function () {
    EventProduct::factory()->count(3)->create([
        'event_id' => $this->event->id,
    ]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('can create an event product', function () {
    $data = [
        'category' => 'Layanan Listrik',
        'name' => 'Instalasi Listrik 2200W',
        'description' => 'Paket instalasi listrik 2200W untuk booth',
        'price' => 1500000,
        'unit' => 'set',
        'booth_types' => ['raw_space'],
        'is_active' => true,
    ];

    $response = $this->postJson($this->apiBase, $data);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Instalasi Listrik 2200W')
        ->assertJsonPath('data.category', 'Layanan Listrik')
        ->assertJsonPath('data.price', '1500000.00');

    $this->assertDatabaseHas('event_products', [
        'event_id' => $this->event->id,
        'name' => 'Instalasi Listrik 2200W',
        'category' => 'Layanan Listrik',
    ]);
});

it('validates required fields on create', function () {
    $response = $this->postJson($this->apiBase, []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category', 'name', 'price']);
});

it('can update an event product', function () {
    $product = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Old Name',
    ]);

    $response = $this->putJson("{$this->apiBase}/{$product->id}", [
        'name' => 'Updated Name',
        'price' => 2000000,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name');

    $this->assertDatabaseHas('event_products', [
        'id' => $product->id,
        'name' => 'Updated Name',
    ]);
});

it('can delete an event product', function () {
    $product = EventProduct::factory()->create([
        'event_id' => $this->event->id,
    ]);

    $response = $this->deleteJson("{$this->apiBase}/{$product->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('event_products', ['id' => $product->id]);
});

it('can toggle product active status', function () {
    $product = EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'is_active' => true,
    ]);

    $response = $this->putJson("{$this->apiBase}/{$product->id}", [
        'is_active' => false,
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('event_products', [
        'id' => $product->id,
        'is_active' => false,
    ]);
});

it('can filter products by category', function () {
    EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category' => 'Listrik',
    ]);
    EventProduct::factory()->create([
        'event_id' => $this->event->id,
        'category' => 'Audio',
    ]);

    $response = $this->getJson("{$this->apiBase}?filter[category]=Listrik");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('validates booth_types values', function () {
    $response = $this->postJson($this->apiBase, [
        'category' => 'Test',
        'name' => 'Test Product',
        'price' => 100000,
        'booth_types' => ['invalid_type'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['booth_types.0']);
});
