<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_brand_sitemap',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
    $this->headers = ['X-API-Key' => 'pk_test_brand_sitemap'];
});

function sitemapSlugs(Project $project, array $headers, string $query = ''): TestResponse
{
    return test()->withHeaders($headers)
        ->getJson("/api/public/projects/{$project->username}/brands-sitemap{$query}");
}

test('returns empty data instead of 404 when the project has no event', function () {
    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.sources', [
            'active' => 0,
            'conjunction' => 0,
            'previous_edition' => 0,
        ]);
});

test('returns slugs from the active event', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $brand = Brand::factory()->create(['slug' => 'kopi-kenangan']);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);

    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'kopi-kenangan')
        ->assertJsonPath('meta.sources.active', 1);
});

test('includes conjunction event brands even when fallback is disabled', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $conjunctionEvent = Event::factory()->published()->create([
        'project_id' => Project::factory(),
        'is_active' => true,
    ]);
    $event->conjunctionEvents()->attach($conjunctionEvent->id, ['order_column' => 1]);

    BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['slug' => 'brand-tetangga']),
        'event_id' => $conjunctionEvent->id,
        'status' => 'active',
    ]);

    sitemapSlugs($this->project, $this->headers, '?fallback=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'brand-tetangga')
        ->assertJsonPath('meta.sources.conjunction', 1)
        ->assertJsonPath('meta.sources.previous_edition', 0);
});

test('includes brands from every published edition, not just the most recent one', function () {
    Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 3,
        'start_date' => now()->addMonths(3),
    ]);

    $oldest = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 1,
        'start_date' => now()->subYears(2),
    ]);
    $previous = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'edition_number' => 2,
        'start_date' => now()->subYear(),
    ]);

    BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['slug' => 'brand-edisi-1']),
        'event_id' => $oldest->id,
        'status' => 'active',
    ]);
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['slug' => 'brand-edisi-2']),
        'event_id' => $previous->id,
        'status' => 'active',
    ]);

    $response = sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonPath('meta.sources.active', 0)
        ->assertJsonPath('meta.sources.previous_edition', 2);

    expect(collect($response->json('data'))->pluck('slug')->sort()->values()->all())
        ->toBe(['brand-edisi-1', 'brand-edisi-2']);
});

test('drops previous editions when the caller sends fallback=0', function () {
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
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $previous->id,
        'status' => 'active',
    ]);

    sitemapSlugs($this->project, $this->headers, '?fallback=0')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.sources.previous_edition', 0);
});

test('drops previous editions when the project disables brand fallback', function () {
    $this->project->update([
        'settings' => ['website_settings' => ['data_fallback' => ['brands' => false]]],
    ]);

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
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $previous->id,
        'status' => 'active',
    ]);

    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.sources.previous_edition', 0);
});

test('emits a brand present in two editions only once, counted against the first source', function () {
    $event = Event::factory()->published()->create([
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

    $returning = Brand::factory()->create(['slug' => 'brand-langganan']);
    BrandEvent::factory()->create([
        'brand_id' => $returning->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);
    BrandEvent::factory()->create([
        'brand_id' => $returning->id,
        'event_id' => $previous->id,
        'status' => 'active',
    ]);

    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'brand-langganan')
        ->assertJsonPath('meta.sources.active', 1)
        ->assertJsonPath('meta.sources.previous_edition', 0);
});

test('excludes brand_event rows that are not active', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $event->id,
        'status' => 'draft',
    ]);

    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.sources.active', 0);
});

test('excludes soft-deleted brands', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);
    $brand->delete();

    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('excludes unpublished editions from the fallback source', function () {
    Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 2,
        'start_date' => now()->addMonths(3),
    ]);

    $draftEdition = Event::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
        'status' => 'draft',
        'edition_number' => 1,
        'start_date' => now()->subYear(),
    ]);
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $draftEdition->id,
        'status' => 'active',
    ]);

    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.sources.previous_edition', 0);
});

test('carries the brand updated_at so the sitemap can emit lastmod', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);

    sitemapSlugs($this->project, $this->headers)
        ->assertOk()
        ->assertJsonPath('data.0.updated_at', $brand->fresh()->updated_at->toIso8601String());
});

test('requires an API key', function () {
    $this->getJson("/api/public/projects/{$this->project->username}/brands-sitemap")
        ->assertUnauthorized();
});
