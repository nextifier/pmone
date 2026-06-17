<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_brand_preview',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
    $this->headers = ['X-API-Key' => 'pk_test_brand_preview'];
});

test('active brands returns empty when active event has no brands and no fallback requested', function () {
    Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 2,
        'start_date' => now()->addMonths(3),
    ]);

    // Previous edition with brands that must NOT leak in without fallback.
    $previous = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 1,
        'start_date' => now()->subYear(),
    ]);
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $previous->id,
        'status' => 'active',
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

test('active brands falls back to the most recent previous edition with brands', function () {
    Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 3,
        'start_date' => now()->addMonths(3),
    ]);

    // Oldest edition with brands.
    $oldest = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 1,
        'start_date' => now()->subYears(2),
    ]);
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $oldest->id,
        'status' => 'active',
    ]);

    // Most recent previous edition with brands (this one should win).
    $recent = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 2,
        'start_date' => now()->subYear(),
    ]);
    BrandEvent::factory()->count(2)->create([
        'brand_id' => Brand::factory(),
        'event_id' => $recent->id,
        'status' => 'active',
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?fallback=1")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

test('active brands does not fall back when the active event already has brands', function () {
    $active = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 2,
        'start_date' => now()->addMonths(3),
    ]);
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $active->id,
        'status' => 'active',
    ]);

    $previous = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 1,
        'start_date' => now()->subYear(),
    ]);
    BrandEvent::factory()->count(5)->create([
        'brand_id' => Brand::factory(),
        'event_id' => $previous->id,
        'status' => 'active',
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?fallback=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1);
});

test('brand detail resolves a brand that only exists in a previous edition', function () {
    Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 2,
        'start_date' => now()->addMonths(3),
    ]);

    $previous = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 1,
        'start_date' => now()->subYear(),
    ]);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $previous->id,
        'status' => 'active',
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands/{$brand->slug}")
        ->assertOk()
        ->assertJsonPath('data.brand_name', $brand->name);
});

test('brand detail picks the most recent edition when a brand spans editions', function () {
    Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 3,
        'start_date' => now()->addMonths(3),
    ]);

    $brand = Brand::factory()->create();

    $oldest = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 1,
        'start_date' => now()->subYears(2),
    ]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $oldest->id,
        'status' => 'active',
        'booth_number' => 'OLD-1',
    ]);

    $recent = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 2,
        'start_date' => now()->subYear(),
    ]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $recent->id,
        'status' => 'active',
        'booth_number' => 'NEW-2',
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands/{$brand->slug}")
        ->assertOk()
        ->assertJsonPath('data.booth_number', 'NEW-2');
});
