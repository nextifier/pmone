<?php

use App\Imports\BrandEventsImport;
use App\Jobs\ExtractOpenGraphMetadata;
use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\ExchangeRate;
use App\Models\Faq;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\PromotionPost;
use App\Models\RoomType;
use App\Models\RundownItem;
use App\Models\ShortLink;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use App\Models\User;
use App\Services\OpenGraph\OpenGraphExtractor;
use App\Support\MediaResponseCacheTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Events\AfterImport;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;
use Spatie\Tags\Tag;

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

test('creating a project payment gateway busts the projects, hotels, events and website-settings caches', function () {
    $spy = ResponseCache::spy();

    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id]);

    $spy->shouldHaveReceived('clear')->with([
        'projects',
        'hotels',
        "events:{$this->project->username}",
        "website-settings:{$this->project->username}",
    ]);
});

test('deactivating a project payment gateway busts the projects, hotels, events and website-settings caches', function () {
    $gateway = ProjectPaymentGateway::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $spy = ResponseCache::spy();

    $gateway->update(['is_active' => false]);

    $spy->shouldHaveReceived('clear')->with([
        'projects',
        'hotels',
        "events:{$this->project->username}",
        "website-settings:{$this->project->username}",
    ]);
});

// ---------------------------------------------------------------------------
// Users (P2) — display fields / avatar feed cached member lists + bylines
// ---------------------------------------------------------------------------
test('updating a user busts short-links and blog-posts caches', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $this->putJson("/api/users/{$target->username}", [
        'name' => 'Renamed Author',
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links', 'blog-posts']);
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
test('saving a link owned by a project busts the projects, events and faqs caches', function () {
    $spy = ResponseCache::spy();

    $this->project->links()->create(['label' => 'Website', 'url' => 'https://example.com']);

    $spy->shouldHaveReceived('clear')->with(['projects', 'events', 'faqs']);
});

test('changing a user public profile field via the model busts short-links and blog-posts', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    $spy = ResponseCache::spy();

    $target->update(['title' => 'Head of Events']);

    $spy->shouldHaveReceived('clear')->with(['short-links', 'blog-posts']);
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

// ---------------------------------------------------------------------------
// Gap A (plan 013) — editing a project link (whatsapp_link/instagram tokens)
// must refresh the cached public FAQ payload end-to-end, not just via spy.
// ---------------------------------------------------------------------------
test('editing a project whatsapp link refreshes the cached public faq answer', function () {
    ResponseCache::clear();
    ApiConsumer::factory()->create(['api_key' => 'pk_test_faq_link', 'is_active' => true]);

    $link = $this->project->links()->create([
        'label' => 'WhatsApp',
        'url' => 'https://wa.me/OLDNUMBER',
    ]);

    Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Contact us?', 'id' => 'x'],
        'answer' => ['en' => '<a href="{{whatsapp_link}}">chat</a>', 'id' => 'x'],
        'order_column' => 1,
    ]);

    $fetch = fn () => $this->withHeaders(['X-API-Key' => 'pk_test_faq_link'])
        ->getJson("/api/public/projects/{$this->project->username}/events/{$this->event->slug}/faqs?locale=en");

    expect($fetch()->assertOk()->json('data.0.a'))->toContain('OLDNUMBER');

    $link->update(['url' => 'https://wa.me/NEWNUMBER']);

    expect($fetch()->assertOk()->json('data.0.a'))
        ->toContain('NEWNUMBER')
        ->not->toContain('OLDNUMBER');
});

// ---------------------------------------------------------------------------
// Gap B (plan 013) — Ticket / session / price-phase reorder controllers use
// query-builder update() (no model events), so the clear must be manual.
// ---------------------------------------------------------------------------
test('reordering tickets refreshes the cached public tickets listing order end-to-end', function () {
    ResponseCache::clear();
    $this->event->update(['tickets_enabled' => true]);
    ApiConsumer::factory()->create(['api_key' => 'pk_test_ticket_reorder', 'is_active' => true]);

    $first = Ticket::factory()->create(['event_id' => $this->event->id, 'title' => ['en' => 'First', 'id' => 'First']]);
    $second = Ticket::factory()->create(['event_id' => $this->event->id, 'title' => ['en' => 'Second', 'id' => 'Second']]);

    $fetch = fn () => $this->withHeaders(['X-API-Key' => 'pk_test_ticket_reorder'])
        ->getJson("/api/public/events/{$this->event->slug}/tickets");

    expect($fetch()->assertOk()->json('data.0.id'))->toBe($first->id);

    $this->postJson("/api/events/{$this->event->id}/tickets/reorder", [
        'orders' => [
            ['id' => $first->id, 'order' => 2],
            ['id' => $second->id, 'order' => 1],
        ],
    ])->assertOk();

    expect($fetch()->assertOk()->json('data.0.id'))->toBe($second->id);
});

test('reordering ticket sessions busts the tickets cache', function () {
    $this->event->update(['tickets_enabled' => true]);
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    $sessionA = TicketSession::factory()->create(['ticket_id' => $ticket->id]);
    $sessionB = TicketSession::factory()->create(['ticket_id' => $ticket->id]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/events/{$this->event->id}/tickets/{$ticket->slug}/sessions/reorder", [
        'orders' => [
            ['id' => $sessionA->id, 'order' => 2],
            ['id' => $sessionB->id, 'order' => 1],
        ],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['tickets']);
});

test('reordering ticket price phases busts the tickets cache', function () {
    $this->event->update(['tickets_enabled' => true]);
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    $phaseA = TicketPricePhase::factory()->create(['ticket_id' => $ticket->id]);
    $phaseB = TicketPricePhase::factory()->create(['ticket_id' => $ticket->id]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/events/{$this->event->id}/tickets/{$ticket->slug}/price-phases/reorder", [
        'orders' => [
            ['id' => $phaseA->id, 'order' => 2],
            ['id' => $phaseB->id, 'order' => 1],
        ],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['tickets']);
});

// ---------------------------------------------------------------------------
// Gap C (plan 013) — EventDay write paths (model saves covered by the trait;
// reorder/sync/set-active are query-builder-only and need a manual clear).
// ---------------------------------------------------------------------------
// EventObserver auto-derives event days from start_date/end_date whenever
// tickets_enabled is toggled on for an event that already has a date range
// (DAY_SYNC_COLUMNS), so these tests use a dedicated event created WITHOUT a
// date range (or with the range set at creation, not via update) to keep
// day_number assignment deterministic and independent of that side effect.
test('creating an event day busts the tickets cache', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'start_date' => null,
        'end_date' => null,
    ]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/events/{$event->id}/event-days", [
        'day_number' => 1,
        'date' => now()->addDay()->format('Y-m-d'),
    ])->assertCreated();

    $spy->shouldHaveReceived('clear')->with(['tickets']);
});

test('reordering event days busts the tickets cache', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'start_date' => null,
        'end_date' => null,
    ]);
    $dayA = EventDay::factory()->create(['event_id' => $event->id, 'day_number' => 1, 'date' => now()->addDay()]);
    $dayB = EventDay::factory()->create(['event_id' => $event->id, 'day_number' => 2, 'date' => now()->addDays(2)]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/events/{$event->id}/event-days/reorder", [
        'orders' => [
            ['id' => $dayA->id, 'order' => 2],
            ['id' => $dayB->id, 'order' => 1],
        ],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['tickets']);
});

test('syncing event days from the event date range busts the tickets cache', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'start_date' => now()->addDay(),
        'end_date' => now()->addDays(2),
    ]);
    // Out-of-range day: sync deactivates it via a bulk query-builder update.
    EventDay::factory()->create(['event_id' => $event->id, 'date' => now()->subMonth(), 'day_number' => 1]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/events/{$event->id}/event-days/sync")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['tickets']);
});

test('setting active event days busts the tickets cache', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'start_date' => null,
        'end_date' => null,
    ]);
    $dayA = EventDay::factory()->create(['event_id' => $event->id, 'day_number' => 1, 'date' => now()->addDay(), 'is_active' => true]);
    $dayB = EventDay::factory()->create(['event_id' => $event->id, 'day_number' => 2, 'date' => now()->addDays(2), 'is_active' => true]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/events/{$event->id}/event-days/active", [
        'active_ids' => [$dayA->id],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['tickets']);

    expect($dayB->fresh()->is_active)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Gap E (plan 013) — generic media delete on a Project owner (e.g. an OG
// image) bypasses the Project model events; the tag map must clear
// 'website-settings' too (og_pages payload embeds Project OG media).
// ---------------------------------------------------------------------------
test('deleting project media via the generic media endpoint busts the website-settings cache', function () {
    Storage::fake('public');

    $media = $this->project->addMedia(UploadedFile::fake()->image('og.png', 10, 10))
        ->withCustomProperties(['uploaded_by' => $this->user->id])
        ->toMediaCollection('gallery');

    $spy = ResponseCache::spy();

    $this->deleteJson("/api/media/{$media->id}")->assertOk();

    $spy->shouldHaveReceived('clear')->with(['projects', 'events', 'website-settings']);
});

// ---------------------------------------------------------------------------
// Gap F (plan 013) — the trait clear fires on create/update BEFORE the
// rundown poster is attached; a second clear after handleTemporaryUpload
// closes the re-cache race (mirrors PartnerController::store).
// ---------------------------------------------------------------------------
test('creating a rundown item busts the rundown cache twice (trait clear + post-upload clear)', function () {
    $spy = ResponseCache::spy();

    $this->postJson(route('rundown-items.store', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
    ]), [
        'title' => ['en' => 'Opening', 'id' => 'Opening'],
    ])->assertCreated();

    $spy->shouldHaveReceived('clear')->with(['rundown'])->twice();
});

test('updating a rundown item busts the rundown cache twice (trait clear + post-upload clear)', function () {
    $item = RundownItem::factory()->create(['event_id' => $this->event->id]);

    $spy = ResponseCache::spy();

    $this->putJson(route('rundown-items.update', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
        'id' => $item->id,
    ]), [
        'title' => ['en' => 'Updated title', 'id' => 'Updated title'],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['rundown'])->twice();
});

// ---------------------------------------------------------------------------
// Business categories (P1) — Spatie Tag writes fire no owner model events
// ---------------------------------------------------------------------------
test('renaming a business category busts the brands cache', function () {
    $tag = Tag::findOrCreate('F&B', "business_category:{$this->project->id}");

    $spy = ResponseCache::spy();

    $this->putJson("/api/projects/{$this->project->username}/business-categories/{$tag->id}", [
        'name' => 'Food & Beverage',
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['brands']);
});

test('deleting a business category busts the brands cache', function () {
    $tag = Tag::findOrCreate('F&B', "business_category:{$this->project->id}");

    $spy = ResponseCache::spy();

    $this->deleteJson("/api/projects/{$this->project->username}/business-categories/{$tag->id}")
        ->assertOk();

    $spy->shouldHaveReceived('clear')->with(['brands']);
});

test('reordering business categories busts the brands cache', function () {
    $tag = Tag::findOrCreate('F&B', "business_category:{$this->project->id}");

    $spy = ResponseCache::spy();

    $this->putJson("/api/projects/{$this->project->username}/business-categories/reorder", [
        'orders' => [['id' => $tag->id, 'order' => 2]],
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['brands']);
});

// ---------------------------------------------------------------------------
// Queued media conversions — ConversionHasBeenCompletedEvent listener
// ---------------------------------------------------------------------------
test('a completed conversion busts the owner cache tags', function () {
    $media = new Media(['model_type' => RundownItem::class]);

    $spy = ResponseCache::spy();

    event(new ConversionHasBeenCompletedEvent($media, Conversion::create('md')));

    $spy->shouldHaveReceived('clear')->with(['rundown']);
});

test('a completed conversion for an unmapped owner clears nothing', function () {
    $media = new Media(['model_type' => Contact::class]);

    $spy = ResponseCache::spy();

    event(new ConversionHasBeenCompletedEvent($media, Conversion::create('md')));

    $spy->shouldNotHaveReceived('clear');
});

test('the media tag map covers the owners embedded in cached payloads', function () {
    expect(MediaResponseCacheTags::for(RundownItem::class))->toBe(['rundown'])
        ->and(MediaResponseCacheTags::for(User::class))->toBe(['short-links', 'blog-posts'])
        ->and(MediaResponseCacheTags::for(LinkPage::class))->toBe(['short-links'])
        ->and(MediaResponseCacheTags::for(LinkPageItem::class))->toBe(['short-links'])
        ->and(MediaResponseCacheTags::for(Event::class))->toContain('brands')
        ->and(MediaResponseCacheTags::for(Contact::class))->toBe([]);
});

test('uploading a rundown poster via the generic media endpoint busts the rundown cache', function () {
    Storage::fake('public');

    $item = RundownItem::factory()->create(['event_id' => $this->event->id]);

    $spy = ResponseCache::spy();

    $this->post('/api/media/upload', [
        'file' => UploadedFile::fake()->image('poster.png', 10, 10),
        'collection' => 'poster',
        'model_type' => RundownItem::class,
        'model_id' => $item->id,
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['rundown']);
});

// ---------------------------------------------------------------------------
// Promotion-posts gates — brand/event/project writes must bust the tag
// ---------------------------------------------------------------------------
test('soft deleting a brand busts the brands and promotion-posts caches', function () {
    $brand = Brand::factory()->create();

    $spy = ResponseCache::spy();

    $brand->delete();

    $spy->shouldHaveReceived('clear')->with(['brands', 'promotion-posts']);
});

test('event and project settings tags include promotion-posts', function () {
    $eventTags = (new ReflectionMethod(Event::class, 'responseCacheTags'))->invoke(null);

    expect($eventTags)->toContain('promotion-posts')
        ->and(Project::SETTINGS_RESPONSE_CACHE_TAGS)->toContain('promotion-posts')
        ->and($this->project->settingsResponseCacheTags())->toContain("promotion-posts:{$this->project->username}");
});

// ---------------------------------------------------------------------------
// Exchange rates — hotels embed estimated_price derived from the latest rate
// ---------------------------------------------------------------------------
test('creating an exchange rate busts the exchange-rates and hotels caches', function () {
    $spy = ResponseCache::spy();

    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['IDR' => 16000],
        'fetched_at' => now(),
    ]);

    $spy->shouldHaveReceived('clear')->with(['exchange-rates', 'hotels']);
});

test('the exchange rate listing ships a stale threshold instead of a frozen is_stale flag', function () {
    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['IDR' => 16000],
        'fetched_at' => now(),
    ]);

    $this->getJson('/api/exchange-rates')
        ->assertOk()
        ->assertJsonPath('meta.stale_after_minutes', 120)
        ->assertJsonMissingPath('meta.is_stale');
});

// ---------------------------------------------------------------------------
// Event poster — embedded in the cached brand-detail payload
// ---------------------------------------------------------------------------
test('removing an event poster busts the events and brands caches', function () {
    $spy = ResponseCache::spy();

    $this->putJson(route('events.update', [
        'username' => $this->project->username,
        'eventSlug' => $this->event->slug,
    ]), [
        'delete_poster_image' => true,
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['events', 'brands']);
});

// ---------------------------------------------------------------------------
// Clear-then-mutate races — an explicit clear must land AFTER media writes
// ---------------------------------------------------------------------------
test('creating a project busts the projects cache after all writes', function () {
    $spy = ResponseCache::spy();

    $this->postJson('/api/projects', [
        'name' => 'Cache Race Project',
        'username' => 'cache_race_project',
        'status' => 'active',
        'visibility' => 'public',
    ])->assertCreated();

    $spy->shouldHaveReceived('clear')->with(['projects']);
});

test('exhibitor brand update busts brands again after category and media writes', function () {
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->assignRole('exhibitor');

    $brand = Brand::factory()->create();
    $brand->users()->attach($exhibitor->id);

    $this->actingAs($exhibitor);

    $spy = ResponseCache::spy();

    $this->putJson("/api/exhibitor/brands/{$brand->slug}", [
        'description' => 'Updated description',
    ])->assertOk();

    // Trait clear (now tagged with promotion-posts) plus the trailing guard.
    $spy->shouldHaveReceived('clear')->with(['brands', 'promotion-posts']);
    $spy->shouldHaveReceived('clear')->with(['brands']);
});

test('updating a link page busts short-links twice (trait clear + post-media clear)', function () {
    $linkPage = LinkPage::factory()->create(['user_id' => $this->user->id]);

    $spy = ResponseCache::spy();

    $this->putJson("/api/link-pages/{$linkPage->slug}", [
        'title' => 'Updated Title',
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links'])->twice();
});

test('creating a link page item busts short-links twice (trait clear + post-upload clear)', function () {
    $linkPage = LinkPage::factory()->create(['user_id' => $this->user->id]);

    $spy = ResponseCache::spy();

    $this->postJson("/api/link-pages/{$linkPage->slug}/items", [
        'label' => 'My Link',
        'url' => 'https://example.com',
    ])->assertCreated();

    $spy->shouldHaveReceived('clear')->with(['short-links'])->twice();
});

test('updating a link page item busts short-links twice (trait clear + post-upload clear)', function () {
    $linkPage = LinkPage::factory()->create(['user_id' => $this->user->id]);
    $item = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id]);

    $spy = ResponseCache::spy();

    $this->putJson("/api/link-pages/{$linkPage->slug}/items/{$item->id}", [
        'label' => 'Renamed Link',
    ])->assertOk();

    $spy->shouldHaveReceived('clear')->with(['short-links'])->twice();
});

// ---------------------------------------------------------------------------
// Write paths that bypass model events entirely
// ---------------------------------------------------------------------------
test('the brand events import clears brands after import', function () {
    $import = new BrandEventsImport($this->event->id);

    $spy = ResponseCache::spy();

    $callbacks = $import->registerEvents();
    $callbacks[AfterImport::class](Mockery::mock(AfterImport::class));

    $spy->shouldHaveReceived('clear')->with(['brands']);
});

test('posts:generate-meta busts the blog-posts cache after quiet saves', function () {
    // The model auto-fills meta on save, so blank the columns quietly to
    // simulate the legacy rows the command exists to backfill.
    $post = Post::factory()->create();
    $post->forceFill(['meta_title' => null, 'meta_description' => null])->saveQuietly();

    $spy = ResponseCache::spy();

    $this->artisan('posts:generate-meta', ['--force' => true])->assertSuccessful();

    $spy->shouldHaveReceived('clear')->with(['blog-posts']);
});
