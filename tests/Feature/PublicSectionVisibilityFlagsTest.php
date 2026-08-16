<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_visibility',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 1,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $this->brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    $this->headers = ['X-API-Key' => 'pk_test_visibility'];
});

test('a brand-new event is visible on both surfaces', function () {
    expect($this->event->brands_public_visible)->toBeTrue()
        ->and($this->event->rundown_public_visible)->toBeTrue();
});

test('brand listings return their empty shape without dropping meta keys', function (string $path, array $expected) {
    $this->event->update(['brands_public_visible' => false]);

    $url = str_replace(
        ['{u}', '{slug}', '{n}', '{brand}'],
        [$this->project->username, $this->event->slug, (string) $this->event->edition_number, $this->brand->slug],
        $path,
    );

    $this->withHeaders($this->headers)
        ->getJson($url)
        ->assertOk()
        ->assertExactJson($expected);
})->with([
    'active brands' => ['/api/public/projects/{u}/brands', [
        'data' => [],
        'meta' => [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 200,
            'total' => 0,
            'fallback' => ['is_fallback' => false, 'source_event' => null],
        ],
    ]],
    'event brands' => ['/api/public/projects/{u}/events/{slug}/brands', [
        'data' => [],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 30, 'total' => 0],
    ]],
    'edition brands' => ['/api/public/projects/{u}/editions/{n}/brands', [
        'data' => [],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 200, 'total' => 0],
    ]],
    'promotion posts' => ['/api/public/projects/{u}/events/{slug}/brands/{brand}/promotion-posts', [
        'data' => [],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 30, 'total' => 0],
    ]],
    'conjunctions' => ['/api/public/projects/{u}/brands-with-conjunctions', [
        'data' => ['groups' => []],
    ]],
    'sitemap' => ['/api/public/projects/{u}/brands-sitemap', [
        'data' => [],
        'meta' => ['sources' => ['active' => 0, 'conjunction' => 0, 'previous_edition' => 0]],
    ]],
]);

test('the empty listing still echoes the caller clamped per_page', function () {
    $this->event->update(['brands_public_visible' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?per_page=50")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});

test('brand detail endpoints 404 when hidden and 200 when forced', function (string $path) {
    $this->event->update(['brands_public_visible' => false]);

    $url = str_replace(
        ['{u}', '{slug}', '{n}', '{brand}'],
        [$this->project->username, $this->event->slug, (string) $this->event->edition_number, $this->brand->slug],
        $path,
    );

    $this->withHeaders($this->headers)->getJson($url.'?fallback=0')->assertNotFound();
    $this->withHeaders($this->headers)->getJson($url.'?force_show_brands=1')->assertOk();
})->with([
    '/api/public/projects/{u}/brands/{brand}',
    '/api/public/projects/{u}/events/{slug}/brands/{brand}',
    '/api/public/projects/{u}/editions/{n}/brands/{brand}',
]);

test('the active pseudo-slug resolves the same event and honours both flags', function () {
    $this->event->update([
        'brands_public_visible' => false,
        'rundown_public_visible' => false,
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/events/active/brands")
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/events/active/rundown")
        ->assertOk()
        ->assertJsonPath('data.days', []);
});

test('the two flags are independent', function () {
    $this->event->update(['brands_public_visible' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/events/active/rundown")
        ->assertOk()
        ->assertJsonPath('data.settings.show_rundown_on_home_page', false);

    $this->event->update(['brands_public_visible' => true, 'rundown_public_visible' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('the public event payload exposes both flags', function () {
    $this->event->update(['brands_public_visible' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/events/active")
        ->assertOk()
        ->assertJsonPath('data.brands_public_visible', false)
        ->assertJsonPath('data.rundown_public_visible', true);
});

test('a bare force parameter with no value is truthy', function () {
    $this->event->update(['brands_public_visible' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?force_show_brands")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('force_show_brands=0 does not bypass the flag', function () {
    $this->event->update(['brands_public_visible' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?force_show_brands=0")
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

test('a rundown force parameter does not unlock brands', function () {
    $this->event->update(['brands_public_visible' => false]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?force_show_rundown=1")
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

test('a forced response is never written to the response cache', function () {
    ResponseCache::clear();

    $forced = fn () => $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?force_show_brands=1");

    expect($forced()->assertOk()->json('meta.total'))->toBe(1);

    // updateQuietly fires no model events, so nothing clears the cache. If the
    // forced URL had been stored, this would still read 1.
    $this->event->updateQuietly(['brands_public_visible' => false]);
    BrandEvent::query()->where('event_id', $this->event->id)->delete();

    expect($forced()->assertOk()->json('meta.total'))->toBe(0);
});

test('the forced URL and the public URL are independent cache entries', function () {
    ResponseCache::clear();

    $public = fn () => $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands");
    $forced = fn () => $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->project->username}/brands?force_show_brands=1");

    // Prime the PUBLIC entry while brands are visible.
    expect($public()->assertOk()->json('meta.total'))->toBe(1);

    // Hide them quietly: nothing busts the cache.
    $this->event->updateQuietly(['brands_public_visible' => false]);

    // The public entry is warm and still says 1, and the forced request neither
    // reads from nor poisons it.
    expect($public()->assertOk()->json('meta.total'))->toBe(1);
    expect($forced()->assertOk()->json('meta.total'))->toBe(1);

    ResponseCache::clear();

    // Recomputed: the two URLs diverge, proving separate keys.
    expect($public()->assertOk()->json('meta.total'))->toBe(0);
    expect($forced()->assertOk()->json('meta.total'))->toBe(1);
});
