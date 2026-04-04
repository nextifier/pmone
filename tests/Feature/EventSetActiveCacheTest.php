<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

    $this->apiConsumer = ApiConsumer::factory()->create([
        'name' => 'Test Website',
        'api_key' => 'pk_test_cache_key',
        'is_active' => true,
        'rate_limit' => 60,
        'allowed_origins' => ['https://example.com'],
    ]);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');

    $this->project = Project::factory()->create();

    $this->event1 = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 1,
    ]);

    $this->event2 = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 2,
    ]);

    $brand1 = Brand::factory()->create(['name' => 'Brand Event One']);
    $brand2 = Brand::factory()->create(['name' => 'Brand Event Two']);

    BrandEvent::factory()->create([
        'brand_id' => $brand1->id,
        'event_id' => $this->event1->id,
        'status' => 'active',
    ]);

    BrandEvent::factory()->create([
        'brand_id' => $brand2->id,
        'event_id' => $this->event2->id,
        'status' => 'active',
    ]);
});

test('setActive clears brands response cache', function () {
    $username = $this->project->username;

    // Prime the brands cache - should return brand from event1 (currently active)
    $response1 = $this->withHeaders(['X-API-Key' => 'pk_test_cache_key'])
        ->getJson("/api/public/projects/{$username}/brands");

    $response1->assertOk();
    expect($response1->json('meta.total'))->toBe(1);

    // Switch active event to event2
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$username}/events/{$this->event2->slug}/set-active");

    // Fetch brands again - should now return brand from event2
    $response2 = $this->withHeaders(['X-API-Key' => 'pk_test_cache_key'])
        ->getJson("/api/public/projects/{$username}/brands");

    $response2->assertOk();
    expect($response2->json('meta.total'))->toBe(1);

    // Verify it's the brand from event2, not event1
    $brandNames = collect($response2->json('data'))->pluck('brand_name')->all();
    expect($brandNames)->toContain('Brand Event Two');
    expect($brandNames)->not->toContain('Brand Event One');
});

test('setActive clears promotion-posts response cache', function () {
    $username = $this->project->username;

    // Prime the events cache
    $response1 = $this->withHeaders(['X-API-Key' => 'pk_test_cache_key'])
        ->getJson("/api/public/projects/{$username}/events/active");

    $response1->assertOk();
    expect($response1->json('data.slug'))->toBe($this->event1->slug);

    // Switch active event
    $this->actingAs($this->user)
        ->postJson("/api/projects/{$username}/events/{$this->event2->slug}/set-active");

    // Events cache should also be cleared (via Event model's ClearsResponseCache)
    $response2 = $this->withHeaders(['X-API-Key' => 'pk_test_cache_key'])
        ->getJson("/api/public/projects/{$username}/events/active");

    $response2->assertOk();
    expect($response2->json('data.slug'))->toBe($this->event2->slug);
});
