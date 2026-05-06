<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Click;
use App\Models\Event;
use App\Models\Link;
use App\Models\Project;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $analyticsPermission = Permission::firstOrCreate(['name' => 'analytics.view', 'guard_name' => 'web']);

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->givePermissionTo($analyticsPermission);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);

    $this->brand = Brand::factory()->create(['status' => 'active']);

    $this->project = Project::factory()->create(['username' => 'test-project']);

    $this->event2026 = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'edition-2026',
        'edition_number' => 26,
    ]);
    $this->event2027 = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'edition-2027',
        'edition_number' => 27,
    ]);

    $this->brandEvent2026 = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event2026->id,
        'status' => 'active',
    ]);
    $this->brandEvent2027 = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event2027->id,
        'status' => 'active',
    ]);

    $this->member = User::factory()->create();
    $this->member->assignRole('exhibitor');
    $this->brand->users()->attach($this->member->id, ['role' => 'owner']);

    $this->outsider = User::factory()->create();
    $this->outsider->assignRole('exhibitor');

    $this->masterUser = User::factory()->create();
    $this->masterUser->assignRole('master');
});

// ============================================================
// Tracking endpoints accept BrandEvent
// ============================================================

test('POST /api/track/visit records a visit on BrandEvent', function () {
    $response = $this->postJson('/api/track/visit', [
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2026->id,
    ]);

    $response->assertStatus(201);

    expect(Visit::query()
        ->where('visitable_type', BrandEvent::class)
        ->where('visitable_id', $this->brandEvent2026->id)
        ->count())->toBe(1);
});

test('POST /api/track/click records a click on BrandEvent with link_label', function () {
    $response = $this->postJson('/api/track/click', [
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2026->id,
        'link_label' => 'Instagram',
    ]);

    $response->assertStatus(201);

    expect(Click::query()
        ->where('clickable_type', BrandEvent::class)
        ->where('clickable_id', $this->brandEvent2026->id)
        ->where('link_label', 'Instagram')
        ->count())->toBe(1);
});

test('bot user-agent does not record a BrandEvent visit', function () {
    $response = $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])
        ->postJson('/api/track/visit', [
            'visitable_type' => BrandEvent::class,
            'visitable_id' => $this->brandEvent2026->id,
        ]);

    $response->assertStatus(204);

    expect(Visit::query()->where('visitable_type', BrandEvent::class)->count())->toBe(0);
});

test('rejects unsupported visitable_type', function () {
    $response = $this->postJson('/api/track/visit', [
        'visitable_type' => 'App\\Models\\NotAModel',
        'visitable_id' => 1,
    ]);

    $response->assertStatus(422);
});

test('rejects deprecated direct Brand tracking', function () {
    $response = $this->postJson('/api/track/visit', [
        'visitable_type' => Brand::class,
        'visitable_id' => $this->brand->id,
    ]);

    $response->assertStatus(422);

    $clickResponse = $this->postJson('/api/track/click', [
        'clickable_type' => Brand::class,
        'clickable_id' => $this->brand->id,
        'link_label' => 'Instagram',
    ]);

    $clickResponse->assertStatus(422);
});

// ============================================================
// Per-event (BrandEvent) analytics
// ============================================================

test('brand member can fetch per-event visits analytics', function () {
    Visit::factory()->count(3)->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2026->id,
        'visited_at' => now(),
    ]);

    Visit::factory()->count(2)->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2027->id,
        'visited_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/visits?type=brand_event&id={$this->brandEvent2026->id}&days=7");

    $response->assertSuccessful();
    expect($response->json('data.summary.total_visits'))->toBe(3);
});

test('per-event clicks analytics use parent brand links and exclude other editions', function () {
    Link::factory()->create([
        'linkable_type' => Brand::class,
        'linkable_id' => $this->brand->id,
        'label' => 'instagram',
        'url' => 'https://instagram.com/brand',
    ]);

    Click::factory()->count(2)->create([
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2026->id,
        'link_label' => 'instagram',
        'clicked_at' => now(),
    ]);
    Click::factory()->create([
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2027->id,
        'link_label' => 'instagram',
        'clicked_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/clicks?type=brand_event&id={$this->brandEvent2026->id}&days=7");

    $response->assertSuccessful();
    expect($response->json('data.summary.total_clicks'))->toBe(2);

    $links = collect($response->json('data.links'));
    expect($links->firstWhere('label', 'instagram')['clicks'])->toBe(2);
});

test('non-member cannot fetch per-event analytics', function () {
    $response = $this->actingAs($this->outsider)
        ->getJson("/api/analytics/visits?type=brand_event&id={$this->brandEvent2026->id}");

    $response->assertForbidden();
});

test('master with permission can fetch per-event analytics', function () {
    $response = $this->actingAs($this->masterUser)
        ->getJson("/api/analytics/visits?type=brand_event&id={$this->brandEvent2026->id}&days=7");

    $response->assertSuccessful();
});

// ============================================================
// Global (Brand) analytics aggregates across all editions
// ============================================================

test('brand global visits aggregate across all the brands BrandEvents', function () {
    Visit::factory()->count(3)->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2026->id,
        'visited_at' => now(),
    ]);
    Visit::factory()->count(2)->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2027->id,
        'visited_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/visits?type=brand&id={$this->brand->id}&days=7");

    $response->assertSuccessful();
    expect($response->json('data.summary.total_visits'))->toBe(5);
});

test('brand global clicks aggregate across all the brands BrandEvents', function () {
    Link::factory()->create([
        'linkable_type' => Brand::class,
        'linkable_id' => $this->brand->id,
        'label' => 'website',
        'url' => 'https://example.com',
    ]);

    Click::factory()->count(2)->create([
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2026->id,
        'link_label' => 'website',
        'clicked_at' => now(),
    ]);
    Click::factory()->create([
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2027->id,
        'link_label' => 'website',
        'clicked_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/clicks?type=brand&id={$this->brand->id}&days=7");

    $response->assertSuccessful();
    expect($response->json('data.summary.total_clicks'))->toBe(3);

    $links = collect($response->json('data.links'));
    expect($links->firstWhere('label', 'website')['clicks'])->toBe(3);
});

test('brand global summary aggregates visits and clicks', function () {
    Visit::factory()->count(2)->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2026->id,
        'visited_at' => now(),
    ]);
    Click::factory()->create([
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2027->id,
        'link_label' => 'instagram',
        'clicked_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/summary?type=brand&id={$this->brand->id}&days=7");

    $response->assertSuccessful();
    expect($response->json('data.total_visits'))->toBe(2);
    expect($response->json('data.total_clicks'))->toBe(1);
});

test('non-member cannot fetch brand global analytics', function () {
    $response = $this->actingAs($this->outsider)
        ->getJson("/api/analytics/visits?type=brand&id={$this->brand->id}");

    $response->assertForbidden();
});

test('analytics endpoint returns 404 for unknown brand_event id', function () {
    $response = $this->actingAs($this->masterUser)
        ->getJson('/api/analytics/visits?type=brand_event&id=999999&days=7');

    $response->assertNotFound();
});

// ============================================================
// Per-event breakdown (drill-down) on Brand global analytics
// ============================================================

test('brand global visits response includes per_event_breakdown', function () {
    Visit::factory()->count(3)->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2026->id,
        'visited_at' => now(),
    ]);
    Visit::factory()->count(2)->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2027->id,
        'visited_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/visits?type=brand&id={$this->brand->id}&days=7");

    $response->assertSuccessful();

    $breakdown = collect($response->json('data.per_event_breakdown'));
    expect($breakdown->count())->toBe(2);

    $entry26 = $breakdown->firstWhere('brand_event_id', $this->brandEvent2026->id);
    $entry27 = $breakdown->firstWhere('brand_event_id', $this->brandEvent2027->id);

    expect($entry26)->not->toBeNull();
    expect($entry26['visits'])->toBe(3);
    expect($entry26['event']['edition_number'])->toBe(26);
    expect($entry26['event']['project_username'])->toBe('test-project');
    expect($entry27['visits'])->toBe(2);
});

test('brand global clicks response includes per_event_breakdown and per-link distribution', function () {
    Link::factory()->create([
        'linkable_type' => Brand::class,
        'linkable_id' => $this->brand->id,
        'label' => 'instagram',
        'url' => 'https://instagram.com/brand',
    ]);

    Click::factory()->count(2)->create([
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2026->id,
        'link_label' => 'instagram',
        'clicked_at' => now(),
    ]);
    Click::factory()->create([
        'clickable_type' => BrandEvent::class,
        'clickable_id' => $this->brandEvent2027->id,
        'link_label' => 'instagram',
        'clicked_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/clicks?type=brand&id={$this->brand->id}&days=7");

    $response->assertSuccessful();

    $breakdown = collect($response->json('data.per_event_breakdown'));
    expect($breakdown)->not->toBeEmpty();

    $entry26 = $breakdown->firstWhere('brand_event_id', $this->brandEvent2026->id);
    expect($entry26['clicks'])->toBe(2);

    $links = collect($response->json('data.links'));
    $instagramLink = $links->firstWhere('label', 'instagram');
    expect($instagramLink)->not->toBeNull();
    expect($instagramLink['clicks'])->toBe(3);

    $perEvent = collect($instagramLink['per_event']);
    expect($perEvent->count())->toBe(2);

    $perEvent26 = $perEvent->firstWhere('brand_event_id', $this->brandEvent2026->id);
    expect($perEvent26['edition_number'])->toBe(26);
    expect($perEvent26['clicks'])->toBe(2);
});

test('brand_event response does not include per_event_breakdown', function () {
    Visit::factory()->create([
        'visitable_type' => BrandEvent::class,
        'visitable_id' => $this->brandEvent2026->id,
        'visited_at' => now(),
    ]);

    $response = $this->actingAs($this->member)
        ->getJson("/api/analytics/visits?type=brand_event&id={$this->brandEvent2026->id}&days=7");

    $response->assertSuccessful();
    expect($response->json('data.per_event_breakdown'))->toBeNull();

    $clicksResponse = $this->actingAs($this->member)
        ->getJson("/api/analytics/clicks?type=brand_event&id={$this->brandEvent2026->id}&days=7");

    $clicksResponse->assertSuccessful();
    expect($clicksResponse->json('data.per_event_breakdown'))->toBeNull();
});
