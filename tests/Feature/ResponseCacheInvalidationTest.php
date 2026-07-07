<?php

use App\Jobs\ExtractOpenGraphMetadata;
use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\PromotionPost;
use App\Models\RoomType;
use App\Models\ShortLink;
use App\Models\User;
use App\Services\OpenGraph\OpenGraphExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'edition_number' => 1,
    ]);
});

/*
 * Each test asserts that the admin write path which BYPASSES Eloquent model
 * events (raw SQL, pivot attach/detach, MediaLibrary, builder mass ops,
 * updateQuietly) still busts the relevant spatie/laravel-responsecache tag.
 * ResponseCache is spied so we assert the explicit clear() the fix adds.
 */

// ---------------------------------------------------------------------------
// Partners (P1) — PartnerCategoryController pivot + query-builder reorder
// ---------------------------------------------------------------------------
test('adding a partner to a category busts the partners cache', function () {
    $category = $this->event->partnerCategories()->create(['name' => 'Sponsors']);
    $partner = Partner::factory()->create();

    $spy = ResponseCache::spy();

    $this->postJson(route('partner-categories.add-partner', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
        'categorySlug' => $category->slug,
    ]), ['partner_id' => $partner->id])->assertCreated();

    $spy->shouldHaveReceived('clear')->with(['partners']);
});

test('removing a partner from a category busts the partners cache', function () {
    $category = $this->event->partnerCategories()->create(['name' => 'Sponsors']);
    $partner = Partner::factory()->create();
    $category->partners()->attach($partner->id, ['order_column' => 1]);
    $pivotId = $category->partners()->first()->pivot->id;

    $spy = ResponseCache::spy();

    $this->deleteJson(route('partner-categories.remove-partner', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
        'categorySlug' => $category->slug,
        'pivotId' => $pivotId,
    ]))->assertOk();

    $spy->shouldHaveReceived('clear')->with(['partners']);
});

test('reordering partner categories busts the partners cache', function () {
    $a = $this->event->partnerCategories()->create(['name' => 'A']);
    $b = $this->event->partnerCategories()->create(['name' => 'B']);

    $spy = ResponseCache::spy();

    $this->postJson(route('partner-categories.update-order', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
    ]), ['order' => [$b->id, $a->id]])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['partners']);
});

// ---------------------------------------------------------------------------
// Events (P2) — EventController::updateOrder raw DB::statement
// ---------------------------------------------------------------------------
test('reordering events busts the events cache', function () {
    // updateOrder uses a PostgreSQL-specific `?::integer` cast in raw SQL that
    // SQLite cannot parse; production runs on PostgreSQL.
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Event reorder raw SQL is PostgreSQL-only.');
    }

    $second = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'edition_number' => 2,
    ]);

    $spy = ResponseCache::spy();

    $this->postJson(route('events.update-order', ['username' => $this->project->username]), [
        'orders' => [
            ['id' => $second->id, 'order' => 1],
            ['id' => $this->event->id, 'order' => 2],
        ],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['events']);
});

// ---------------------------------------------------------------------------
// Brands (P1) — BrandEventController::updateOrder raw DB::statement
// ---------------------------------------------------------------------------
test('reordering brands busts the brands cache', function () {
    // updateOrder uses a PostgreSQL-specific `?::integer` cast in raw SQL that
    // SQLite cannot parse; production runs on PostgreSQL.
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Brand reorder raw SQL is PostgreSQL-only.');
    }

    $brandEventA = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);
    $brandEventB = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    $spy = ResponseCache::spy();

    $this->postJson(route('brand-events.update-order', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
    ]), [
        'orders' => [
            ['id' => $brandEventB->id, 'order' => 1],
            ['id' => $brandEventA->id, 'order' => 2],
        ],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['brands', 'promotion-posts']);
});

// ---------------------------------------------------------------------------
// Promotion posts (P2) — reorder media via Media::setNewOrder
// ---------------------------------------------------------------------------
test('reordering promotion post media busts the brands cache', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);
    $post = PromotionPost::factory()->create(['brand_event_id' => $brandEvent->id]);

    $spy = ResponseCache::spy();

    $this->postJson(route('brand-events.promotion-posts.reorder-media', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
        'brandSlug' => $brand->slug,
        'postId' => $post->id,
    ]), ['media_ids' => [1]])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['brands', 'promotion-posts']);
});

// ---------------------------------------------------------------------------
// Blog posts (P2) — PostController bulk builder ops
// ---------------------------------------------------------------------------
test('bulk deleting posts busts the blog-posts cache', function () {
    $posts = Post::factory()->count(2)->create(['created_by' => $this->user->id]);

    $spy = ResponseCache::spy();

    $this->deleteJson(route('posts.bulk-destroy'), [
        'ids' => $posts->pluck('id')->all(),
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['blog-posts']);
});

test('bulk restoring posts busts the blog-posts cache', function () {
    $posts = Post::factory()->count(2)->create(['created_by' => $this->user->id]);
    Post::whereIn('id', $posts->pluck('id'))->delete();

    $spy = ResponseCache::spy();

    $this->postJson(route('posts.bulk-restore'), [
        'ids' => $posts->pluck('id')->all(),
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['blog-posts']);
});

// ---------------------------------------------------------------------------
// Projects (P2) — member pivot + payment gateway observer
// ---------------------------------------------------------------------------
test('toggling a project member busts the projects cache', function () {
    $member = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $this->postJson(route('projects.toggle-member', ['username' => $this->project->username]), [
        'user_id' => $member->id,
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['projects']);
});

test('creating a project payment gateway busts the projects cache', function () {
    $spy = ResponseCache::spy();

    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id]);

    $spy->shouldHaveReceived('clear')->with(['projects']);
});

// ---------------------------------------------------------------------------
// Users (P2) — display fields / avatar feed cached member lists + bylines
// ---------------------------------------------------------------------------
test('updating a user busts short-links, projects and blog-posts caches', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $this->putJson("/api/users/{$target->username}", [
        'name' => 'Renamed Author',
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links', 'projects', 'blog-posts']);
});

test('verifying a user busts the short-links cache', function () {
    $target = User::factory()->unverified()->create();

    $spy = ResponseCache::spy();

    $this->postJson("/api/users/{$target->username}/verify")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links']);
});

test('unverifying a user busts the short-links cache', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/users/{$target->username}/unverify")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links']);
});

// ---------------------------------------------------------------------------
// Short links (P2) — OG metadata job updateQuietly
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// Project settings (P1/P2) — generic update writes website_settings JSON
// ---------------------------------------------------------------------------
test('updating a project with settings busts website-settings, rundown and events caches', function () {
    $spy = ResponseCache::spy();

    $this->putJson("/api/projects/{$this->project->username}", [
        'settings' => ['contact_form' => ['enabled' => true]],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with($this->project->settingsResponseCacheTags());
});

test('updating website settings busts all settings-backed caches', function () {
    $spy = ResponseCache::spy();

    $this->patchJson("/api/projects/{$this->project->username}/website-settings", [
        'home_sections' => ['hero' => false],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with($this->project->settingsResponseCacheTags());
});

test('settings cache tags are tenant-scoped except the global hotels tag', function () {
    $tags = $this->project->settingsResponseCacheTags();

    expect($tags)->toContain("website-settings:{$this->project->username}")
        ->toContain("rundown:{$this->project->username}")
        ->toContain('hotels')
        ->not->toContain('website-settings');
});

// ---------------------------------------------------------------------------
// Project model hook — settings/hotel-toggle writes from ANY code path.
// Asserted end-to-end against the real (array) response cache: a facade spy
// swallows model-event clears in this suite, so we prove the behaviour by
// priming the cached public endpoint and asserting the payload refreshes.
// ---------------------------------------------------------------------------
test('writing project settings via the model busts the cached public website-settings response', function () {
    ResponseCache::clear();
    ApiConsumer::factory()->create(['api_key' => 'pk_test_cache_key', 'is_active' => true]);

    $fetch = fn () => $this->withHeaders(['X-API-Key' => 'pk_test_cache_key'])
        ->getJson("/api/public/projects/{$this->project->username}/website-settings");

    expect($fetch()->assertOk()->json('data.settings.home_sections.hero'))->toBeTrue();

    $this->project->update([
        'settings' => ['website_settings' => ['home_sections' => ['hero' => false]]],
    ]);

    expect($fetch()->assertOk()->json('data.settings.home_sections.hero'))->toBeFalse();
});

test('toggling hotel_reservation_enabled via the model busts the cached public event payload', function () {
    ResponseCache::clear();
    ApiConsumer::factory()->create(['api_key' => 'pk_test_cache_key', 'is_active' => true]);

    $fetch = fn () => $this->withHeaders(['X-API-Key' => 'pk_test_cache_key'])
        ->getJson("/api/public/projects/{$this->project->username}/events/active");

    $initial = $fetch()->assertOk()->json('data.hotel_reservation_enabled');

    $this->project->update(['hotel_reservation_enabled' => ! $initial]);

    expect($fetch()->assertOk()->json('data.hotel_reservation_enabled'))->toBe(! $initial);
});

test('the hotel reservation toggle endpoint busts hotels, events and website-settings caches', function () {
    $this->project->update(['hotel_reservation_enabled' => true]);

    $spy = ResponseCache::spy();

    $this->patchJson("/api/projects/{$this->project->username}/hotel-reservation-toggle", [
        'enabled' => false,
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with([
        'hotels',
        "events:{$this->project->username}",
        "website-settings:{$this->project->username}",
    ]);
});

test('a settings write on one project leaves another project\'s cached responses warm', function () {
    ResponseCache::clear();
    ApiConsumer::factory()->create(['api_key' => 'pk_test_cache_key', 'is_active' => true]);

    $other = Project::factory()->create();

    $fetch = fn (string $username) => $this->withHeaders(['X-API-Key' => 'pk_test_cache_key'])
        ->getJson("/api/public/projects/{$username}/website-settings");

    expect($fetch($this->project->username)->assertOk()->json('data.settings.home_sections.hero'))->toBeTrue();
    expect($fetch($other->username)->assertOk()->json('data.settings.home_sections.hero'))->toBeTrue();

    // Flip BOTH projects' stored settings, but $other quietly (no events, no
    // clear) - its cached response must keep serving the OLD payload.
    $other->updateQuietly([
        'settings' => ['website_settings' => ['home_sections' => ['hero' => false]]],
    ]);
    $this->project->update([
        'settings' => ['website_settings' => ['home_sections' => ['hero' => false]]],
    ]);

    expect($fetch($this->project->username)->assertOk()->json('data.settings.home_sections.hero'))
        ->toBeFalse();
    expect($fetch($other->username)->assertOk()->json('data.settings.home_sections.hero'))
        ->toBeTrue();
});

// ---------------------------------------------------------------------------
// Hotels (P1) — generic media delete bypasses the Hotel saved event
// ---------------------------------------------------------------------------
test('deleting hotel media via the generic media endpoint busts the hotels cache', function () {
    Storage::fake('public');

    $hotel = Hotel::factory()->create();
    $media = $hotel->addMedia(UploadedFile::fake()->image('photo.png', 10, 10))
        ->withCustomProperties(['uploaded_by' => $this->user->id])
        ->toMediaCollection('gallery');

    $spy = ResponseCache::spy();

    $this->deleteJson("/api/media/{$media->id}")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['hotels']);
});

// ---------------------------------------------------------------------------
// Event conjunctions — pivot writes, embedded in cached activeEvent payload
// ---------------------------------------------------------------------------
test('linking a conjunction event busts the brands and events caches', function () {
    $other = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'edition_number' => 2,
    ]);

    $spy = ResponseCache::spy();

    $this->postJson(route('event-conjunctions.store', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
    ]), ['conjunction_event_id' => $other->id])->assertCreated();

    $spy->shouldHaveReceived('clear')->with(['brands', 'events']);
});

// ---------------------------------------------------------------------------
// Generic media upload — never fires the owner's model events
// ---------------------------------------------------------------------------
test('uploading media via the generic endpoint busts the owner cache tags', function () {
    Storage::fake('public');

    $partner = Partner::factory()->create();

    $spy = ResponseCache::spy();

    $this->post('/api/media/upload', [
        'file' => UploadedFile::fake()->image('logo.png', 10, 10),
        'collection' => 'partner_logo',
        'model_type' => Partner::class,
        'model_id' => $partner->id,
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['partners']);
});

// ---------------------------------------------------------------------------
// User public profile (/resolve/{slug}, tag short-links)
// ---------------------------------------------------------------------------
test('saving a link owned by a project busts the projects and events caches', function () {
    $spy = ResponseCache::spy();

    $this->project->links()->create(['label' => 'Website', 'url' => 'https://example.com']);

    $spy->shouldHaveReceived('clear')->with(['projects', 'events']);
});

test('changing a user public profile field via the model busts short-links, projects and blog-posts', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $target->update(['title' => 'Head of Events']);

    $spy->shouldHaveReceived('clear')->with(['short-links', 'projects', 'blog-posts']);
});

test('a last_seen touch does not clear the response cache', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $target->update(['last_seen' => now()]);

    $spy->shouldNotHaveReceived('clear');
});

test('saving a link owned by a user busts the short-links cache', function () {
    $spy = ResponseCache::spy();

    $this->user->links()->create(['label' => 'Website', 'url' => 'https://example.com']);

    $spy->shouldHaveReceived('clear')->with(['short-links']);
});

test('deleting a user busts the short-links cache', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $this->deleteJson("/api/users/{$target->username}")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links']);
});

test('restoring a user busts the short-links cache', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);
    $target->delete();

    $spy = ResponseCache::spy();

    $this->postJson("/api/users/trash/{$target->id}/restore")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links']);
});

// ---------------------------------------------------------------------------
// Hotels — controller paths that skip the Hotel/HotelEvent model events
// ---------------------------------------------------------------------------
test('updating a hotel with a media-only payload busts the hotels cache', function () {
    $hotel = Hotel::factory()->create();

    $spy = ResponseCache::spy();

    $this->putJson("/api/hotels/{$hotel->slug}", [])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['hotels']);
});

test('detaching a hotel from an event busts the hotels cache', function () {
    $this->project->update(['hotel_reservation_enabled' => true]);
    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);

    $hotel = Hotel::factory()->create();
    HotelEvent::create(['hotel_id' => $hotel->id, 'event_id' => $this->event->id, 'is_active' => true]);

    $spy = ResponseCache::spy();

    $this->deleteJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['hotels']);
});

test('reordering hotel media busts the hotels cache', function () {
    $this->project->update(['hotel_reservation_enabled' => true]);
    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);

    $hotel = Hotel::factory()->create();
    HotelEvent::create(['hotel_id' => $hotel->id, 'event_id' => $this->event->id, 'is_active' => true]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}/media/gallery/reorder", [
        'media_ids' => [1],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['hotels']);
});

test('reordering room type media busts the hotels cache', function () {
    $this->project->update(['hotel_reservation_enabled' => true]);
    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);

    $hotel = Hotel::factory()->create();
    HotelEvent::create(['hotel_id' => $hotel->id, 'event_id' => $this->event->id, 'is_active' => true]);
    $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id]);

    $spy = ResponseCache::spy();

    $this->postJson(
        "/api/events/{$this->event->id}/hotels/{$hotel->slug}/room-types/{$roomType->slug}/media/reorder",
        ['media_ids' => [1]],
    )->assertOk();

    $spy->shouldHaveReceived('clear')->with(['hotels']);
});

// ---------------------------------------------------------------------------
// Contacts — tag was orphaned (no route caches 'contacts'); trait removed
// ---------------------------------------------------------------------------
test('saving a contact no longer clears the response cache', function () {
    $spy = ResponseCache::spy();

    Contact::factory()->create();

    $spy->shouldNotHaveReceived('clear');
});

test('OG metadata job busts the short-links cache after updateQuietly', function () {
    $shortLink = ShortLink::factory()->create([
        'user_id' => $this->user->id,
        'destination_url' => 'https://example.com',
    ]);

    $extractor = Mockery::mock(OpenGraphExtractor::class);
    $extractor->shouldReceive('extract')->andReturn([
        'og_title' => 'Title',
        'og_description' => 'Desc',
        'og_image' => 'https://example.com/og.png',
        'og_type' => 'website',
    ]);

    $spy = ResponseCache::spy();

    (new ExtractOpenGraphMetadata($shortLink->id))->handle($extractor);

    $spy->shouldHaveReceived('clear')->with(['short-links']);
});
