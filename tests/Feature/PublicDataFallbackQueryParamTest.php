<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Partner;
use App\Models\PartnerCategory;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

/**
 * Event websites decide previous-edition fallback in their own `app.config.ts`
 * and send it per request as `?fallback=0|1`. These cover the query param;
 * ProjectDataFallbackSettingTest covers the dashboard toggle it is ANDed with.
 */
beforeEach(function () {
    Storage::fake('public');
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_fb_query', 'is_active' => true]);
    $this->headers = ['X-API-Key' => 'pk_test_fb_query'];
});

/** An active event with an older edition that owns all the content. */
function projectWithPreviousEdition(): array
{
    $project = Project::factory()->create();
    $event = Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'edition_number' => 2,
        'start_date' => now()->addMonths(3),
    ]);
    $older = Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => false,
        'edition_number' => 1,
        'start_date' => now()->subYear(),
    ]);

    return [$project, $event, $older];
}

/** Give the older edition one item for the named section. */
function seedSection(string $section, Event $event): void
{
    match ($section) {
        'guests' => Guest::factory()->create(['event_id' => $event->id, 'name' => 'Legacy Speaker']),
        'programs' => Program::factory()->create([
            'event_id' => $event->id, 'title' => ['en' => 'Old'], 'is_active' => true, 'order_column' => 1,
        ]),
        'partners' => (function () use ($event) {
            $category = PartnerCategory::create(['event_id' => $event->id, 'name' => 'Past Sponsors']);
            $partner = Partner::factory()->create(['name' => 'legacy']);
            $partner->addMedia(UploadedFile::fake()->image('legacy.png', 480, 240))
                ->toMediaCollection('partner_logo');
            $category->partners()->attach($partner->id, ['order_column' => 1]);
        })(),
    };
}

// ---------------------------------------------------------------------------
// fallback=0 switches the borrow off, per section
// ---------------------------------------------------------------------------

test('fallback=0 returns the active event own empty data', function (string $section) {
    [$project, $event, $older] = projectWithPreviousEdition();
    seedSection($section, $older);

    $url = "/api/public/projects/{$project->username}/events/{$event->slug}/{$section}";

    // Sanity: without the flag the borrow happens, so a 0 below is the flag
    // working rather than the seed silently doing nothing.
    $this->withHeaders($this->headers)->getJson($url)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)->getJson("{$url}?fallback=0")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.fallback.is_fallback', false);
})->with(['guests', 'partners', 'programs']);

test('omitting the flag keeps borrowing, matching the old default', function (string $section) {
    [$project, $event, $older] = projectWithPreviousEdition();
    seedSection($section, $older);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/{$section}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.fallback.is_fallback', true)
        ->assertJsonPath('meta.fallback.source_event.title', $older->title);
})->with(['guests', 'partners', 'programs']);

test('fallback=1 borrows explicitly', function () {
    [$project, $event, $older] = projectWithPreviousEdition();
    Guest::factory()->create(['event_id' => $older->id, 'name' => 'Legacy Speaker']);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/guests?fallback=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.fallback.is_fallback', true);
});

// ---------------------------------------------------------------------------
// Cache isolation: the two variants must not serve each other
// ---------------------------------------------------------------------------

test('fallback=0 and fallback=1 get separate response-cache entries', function () {
    [$project, $event, $older] = projectWithPreviousEdition();
    Guest::factory()->create(['event_id' => $older->id, 'name' => 'Legacy Speaker']);

    $base = "/api/public/projects/{$project->username}/events/{$event->slug}/guests";

    // Warm the borrowing variant first; the next call must not be served it.
    $this->withHeaders($this->headers)->getJson("{$base}?fallback=1")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)->getJson("{$base}?fallback=0")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ---------------------------------------------------------------------------
// Brands: the listing stays opt-in, the detail page honours the flag
// ---------------------------------------------------------------------------

test('brand listing never borrows unless fallback=1 is passed', function () {
    [$project, $event, $older] = projectWithPreviousEdition();
    BrandEvent::factory()->count(2)->create([
        'brand_id' => Brand::factory(), 'event_id' => $older->id, 'status' => 'active',
    ]);

    $url = "/api/public/projects/{$project->username}/brands";

    $this->withHeaders($this->headers)->getJson($url)
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->withHeaders($this->headers)->getJson("{$url}?fallback=0")
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->withHeaders($this->headers)->getJson("{$url}?fallback=1")
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

test('brand detail from a previous edition 404s when fallback=0', function () {
    [$project, $event, $older] = projectWithPreviousEdition();
    $brand = Brand::factory()->create();
    BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $older->id, 'status' => 'active']);

    $url = "/api/public/projects/{$project->username}/brands/{$brand->slug}";

    $this->withHeaders($this->headers)->getJson($url)->assertOk();
    $this->withHeaders($this->headers)->getJson("{$url}?fallback=0")->assertNotFound();
});

// ---------------------------------------------------------------------------
// The dashboard toggle still wins while it exists
// ---------------------------------------------------------------------------

test('project setting OFF beats fallback=1', function () {
    [$project, $event, $older] = projectWithPreviousEdition();
    $project->update(['settings' => ['website_settings' => ['data_fallback' => ['guests' => false]]]]);
    Guest::factory()->create(['event_id' => $older->id, 'name' => 'Legacy Speaker']);
    ResponseCache::clear();

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/guests?fallback=1")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
