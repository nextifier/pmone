<?php

use App\Jobs\ExtractOpenGraphMetadata;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\PromotionPost;
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

    $spy->shouldHaveReceived('clear')->with(['rundown', 'events', 'website-settings', 'hotels']);
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
