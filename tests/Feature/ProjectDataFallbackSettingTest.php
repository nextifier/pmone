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

beforeEach(function () {
    Storage::fake('public');
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_fallback', 'is_active' => true]);
    $this->headers = ['X-API-Key' => 'pk_test_fallback'];
});

/** Build a project with explicit per-section data_fallback flags. */
function projectWithFallback(array $flags): Project
{
    return Project::factory()->create([
        'settings' => ['website_settings' => ['data_fallback' => $flags]],
    ]);
}

function partnerWithLogoIn(Event $event, string $categoryName, string $partnerName): void
{
    $category = PartnerCategory::create(['event_id' => $event->id, 'name' => $categoryName]);
    $partner = Partner::factory()->create(['name' => $partnerName]);
    $partner->addMedia(UploadedFile::fake()->image("{$partnerName}.png", 480, 240))
        ->toMediaCollection('partner_logo');
    $category->partners()->attach($partner->id, ['order_column' => 1]);
}

// ---------------------------------------------------------------------------
// Brands (activeBrands, opt-in fallback=1 preview)
// ---------------------------------------------------------------------------

test('default project still falls back to a previous edition for brands', function () {
    $project = Project::factory()->create(); // unconfigured => default ON
    Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => true, 'edition_number' => 2, 'start_date' => now()->addMonths(3)]);
    $prev = Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => false, 'edition_number' => 1, 'start_date' => now()->subYear()]);
    BrandEvent::factory()->count(2)->create(['brand_id' => Brand::factory(), 'event_id' => $prev->id, 'status' => 'active']);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/brands?fallback=1")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.fallback.is_fallback', true)
        ->assertJsonPath('meta.fallback.source_event.title', $prev->title);
});

test('setting OFF stops brand fallback even with fallback=1', function () {
    $project = projectWithFallback(['brands' => false]);
    Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => true, 'edition_number' => 2, 'start_date' => now()->addMonths(3)]);
    $prev = Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => false, 'edition_number' => 1, 'start_date' => now()->subYear()]);
    BrandEvent::factory()->count(2)->create(['brand_id' => Brand::factory(), 'event_id' => $prev->id, 'status' => 'active']);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/brands?fallback=1")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.fallback.is_fallback', false);
});

test('setting OFF makes brand detail from a previous edition 404', function () {
    $project = projectWithFallback(['brands' => false]);
    Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => true, 'edition_number' => 2, 'start_date' => now()->addMonths(3)]);
    $prev = Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => false, 'edition_number' => 1, 'start_date' => now()->subYear()]);
    $brand = Brand::factory()->create();
    BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $prev->id, 'status' => 'active']);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/brands/{$brand->slug}")
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Partners (fallbackEventWithItems-backed)
// ---------------------------------------------------------------------------

test('default project still falls back to a previous edition for partners + flags the badge', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2026-06-04 10:00:00']);
    $older = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2025-01-01 10:00:00']);
    partnerWithLogoIn($older, 'Past Sponsors', 'legacy');

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/partners")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category', 'Past Sponsors')
        ->assertJsonPath('meta.fallback.is_fallback', true)
        ->assertJsonPath('meta.fallback.source_event.title', $older->title);
});

test('setting OFF returns empty partners instead of borrowing a previous edition', function () {
    $project = projectWithFallback(['partners' => false]);
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2026-06-04 10:00:00']);
    $older = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2025-01-01 10:00:00']);
    partnerWithLogoIn($older, 'Past Sponsors', 'legacy');

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/partners")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.fallback.is_fallback', false);
});

test('partners with own data are not flagged as fallback', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2026-06-04 10:00:00']);
    partnerWithLogoIn($event, 'Current Sponsors', 'current');

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/partners")
        ->assertOk()
        ->assertJsonPath('data.0.category', 'Current Sponsors')
        ->assertJsonPath('meta.fallback.is_fallback', false);
});

// ---------------------------------------------------------------------------
// Programs (fallbackEventWithItems-backed)
// ---------------------------------------------------------------------------

test('setting OFF returns empty programs instead of borrowing a previous edition', function () {
    $project = projectWithFallback(['programs' => false]);
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2026-06-04 10:00:00']);
    $older = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2025-01-01 10:00:00']);
    Program::factory()->create(['event_id' => $older->id, 'title' => ['en' => 'Old', 'id' => 'Lama'], 'is_active' => true, 'order_column' => 1]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/programs?locale=en")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.fallback.is_fallback', false);
});

// ---------------------------------------------------------------------------
// Guests (NEW fallback - did not exist before)
// ---------------------------------------------------------------------------

test('default project falls back to a previous edition for guests', function () {
    $project = Project::factory()->create(); // default ON
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2026-06-04 10:00:00']);
    $older = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2025-01-01 10:00:00']);
    Guest::factory()->create(['event_id' => $older->id, 'name' => 'Legacy Speaker']);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/guests")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('meta.fallback.is_fallback', true)
        ->assertJsonPath('meta.fallback.source_event.title', $older->title);
});

test('setting OFF returns empty guests instead of borrowing a previous edition', function () {
    $project = projectWithFallback(['guests' => false]);
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2026-06-04 10:00:00']);
    $older = Event::factory()->published()->create(['project_id' => $project->id, 'start_date' => '2025-01-01 10:00:00']);
    Guest::factory()->create(['event_id' => $older->id, 'name' => 'Legacy Speaker']);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events/{$event->slug}/guests")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.fallback.is_fallback', false);
});

test('guest detail resolves a borrowed guest when ON and 404s when OFF', function () {
    $event = Event::factory()->published()->create(['start_date' => '2026-06-04 10:00:00']);
    $older = Event::factory()->published()->create(['project_id' => $event->project_id, 'start_date' => '2025-01-01 10:00:00']);
    $guest = Guest::factory()->create(['event_id' => $older->id, 'name' => 'Borrowed Speaker']);

    // Default ON: detail resolves from the previous edition.
    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$event->project->username}/events/{$event->slug}/guests/{$guest->slug}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Borrowed Speaker');

    // Turn the guests fallback off for this project (the real endpoint busts
    // the response cache; mirror that here since we update the model directly).
    $event->project->update([
        'settings' => ['website_settings' => ['data_fallback' => ['guests' => false]]],
    ]);
    ResponseCache::clear();

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$event->project->username}/events/{$event->slug}/guests/{$guest->slug}")
        ->assertNotFound();
});
