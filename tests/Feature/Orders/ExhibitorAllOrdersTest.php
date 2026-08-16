<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['brands.read', 'brands.update'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web'])
        ->syncPermissions(['brands.read', 'brands.update']);

    $this->exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $this->exhibitor->assignRole('exhibitor');

    $this->project = Project::factory()->create();
});

/**
 * A booth in a fresh event, owned by the given user unless told otherwise.
 */
function boothFor(?User $owner, Project $project, string $eventTitle, string $boothNumber): BrandEvent
{
    $event = Event::factory()->create(['project_id' => $project->id, 'title' => $eventTitle]);
    $brand = Brand::factory()->create(['name' => "Brand {$boothNumber}"]);

    if ($owner) {
        $brand->users()->attach($owner->id, ['role' => 'owner']);
    }

    return BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'booth_number' => $boothNumber,
    ]);
}

it('returns orders across every brand, booth and event the user owns', function () {
    $first = boothFor($this->exhibitor, $this->project, 'Event One', 'A-01');
    $second = boothFor($this->exhibitor, $this->project, 'Event Two', 'B-02');

    Order::factory()->create(['brand_event_id' => $first->id]);
    Order::factory()->create(['brand_event_id' => $first->id]);
    Order::factory()->create(['brand_event_id' => $second->id]);

    $response = $this->actingAs($this->exhibitor)
        ->getJson('/api/exhibitor/orders')
        ->assertSuccessful();

    expect($response->json('data'))->toHaveCount(3)
        ->and(collect($response->json('data'))->pluck('brand_event_id')->unique()->sort()->values()->all())
        ->toBe([$first->id, $second->id]);
});

it('never returns an order belonging to another users brand', function () {
    $mine = boothFor($this->exhibitor, $this->project, 'Mine', 'A-01');
    $theirs = boothFor(null, $this->project, 'Theirs', 'C-03');

    Order::factory()->create(['brand_event_id' => $mine->id]);
    Order::factory()->create(['brand_event_id' => $theirs->id]);

    $response = $this->actingAs($this->exhibitor)
        ->getJson('/api/exhibitor/orders')
        ->assertSuccessful();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.brand_event_id'))->toBe($mine->id);
});

it('carries the event, brand and booth on every order', function () {
    $booth = boothFor($this->exhibitor, $this->project, 'Context Event', 'A-01');
    Order::factory()->create(['brand_event_id' => $booth->id]);

    $order = $this->actingAs($this->exhibitor)
        ->getJson('/api/exhibitor/orders')
        ->assertSuccessful()
        ->json('data.0');

    expect($order['brand_event']['event']['title'])->toBe('Context Event')
        ->and($order['brand_event']['event'])->toHaveKeys(['date_label', 'start_date'])
        ->and($order['brand_event']['brand']['slug'])->toBe($booth->brand->slug)
        ->and($order['brand_event']['booth_number'])->toBe('A-01');
});

it('loads the whole list in a constant number of queries', function () {
    $booth = boothFor($this->exhibitor, $this->project, 'Query Event', 'A-01');
    Order::factory()->create(['brand_event_id' => $booth->id]);

    $this->actingAs($this->exhibitor);

    // Warm-up: the first authenticated request of a test also resolves the user,
    // its roles and its permissions, and those are cached afterwards. Counting
    // it would measure the auth stack, not the eager loading.
    $this->getJson('/api/exhibitor/orders')->assertSuccessful();

    DB::enableQueryLog();
    $this->getJson('/api/exhibitor/orders')->assertSuccessful();
    $withOne = count(DB::getQueryLog());

    $second = boothFor($this->exhibitor, $this->project, 'Query Event Two', 'B-02');
    Order::factory()->count(4)->create(['brand_event_id' => $second->id]);

    DB::flushQueryLog();
    $this->getJson('/api/exhibitor/orders')->assertSuccessful();
    $withFive = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($withFive)->toBe($withOne);
});

it('rejects a guest', function () {
    $this->getJson('/api/exhibitor/orders')->assertUnauthorized();
});
