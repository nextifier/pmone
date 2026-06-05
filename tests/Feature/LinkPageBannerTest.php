<?php

use App\Models\LinkPage;
use App\Models\LinkPageBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
    Storage::fake('local');
    Storage::fake('public');
});

function createBannerUser(array $permissions = []): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

/** Simulate a completed temporary upload and return its folder id. */
function fakeTmpBannerUpload(string $name = 'banner.jpg'): string
{
    $folder = uniqid('tmp-', true);
    $file = UploadedFile::fake()->image($name, 1920, 1080);

    Storage::disk('local')->putFileAs("tmp/uploads/{$folder}", $file, $name);
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => $name,
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'uploaded_at' => now()->toISOString(),
    ]));

    return $folder;
}

// ─── Store (multi-upload) ───────────────────────────────────────────

test('can create banners via multi-upload', function () {
    $user = createBannerUser(['link_pages.read', 'link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);

    $folders = [fakeTmpBannerUpload('a.jpg'), fakeTmpBannerUpload('b.jpg')];

    $response = $this->actingAs($user)->postJson("/api/link-pages/{$linkPage->slug}/banners", [
        'tmp_images' => $folders,
    ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'data');

    expect($linkPage->banners()->count())->toBe(2);

    $banner = $linkPage->banners()->ordered()->first();
    expect($banner->getMedia('image'))->toHaveCount(1);
    expect($banner->sort_order)->toBe(0);
});

test('store requires at least one image', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/api/link-pages/{$linkPage->slug}/banners", [
        'tmp_images' => [],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('tmp_images');
});

test('store appends sort_order after existing banners', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id, 'sort_order' => 5]);

    $this->actingAs($user)->postJson("/api/link-pages/{$linkPage->slug}/banners", [
        'tmp_images' => [fakeTmpBannerUpload()],
    ])->assertCreated();

    expect($linkPage->banners()->max('sort_order'))->toBe(6);
});

// ─── Update ─────────────────────────────────────────────────────────

test('can update banner metadata', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $banner = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id]);

    $response = $this->actingAs($user)->putJson("/api/link-pages/{$linkPage->slug}/banners/{$banner->id}", [
        'url' => 'https://example.com',
        'caption' => 'Promo banner',
        'is_active' => false,
        'starts_at' => '2026-06-01 00:00:00',
        'ends_at' => '2026-06-30 23:59:59',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.url', 'https://example.com')
        ->assertJsonPath('data.caption', 'Promo banner')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('link_page_banners', [
        'id' => $banner->id,
        'url' => 'https://example.com',
        'caption' => 'Promo banner',
    ]);
});

test('rejects end time before start time', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $banner = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id]);

    $response = $this->actingAs($user)->putJson("/api/link-pages/{$linkPage->slug}/banners/{$banner->id}", [
        'starts_at' => '2026-06-30 00:00:00',
        'ends_at' => '2026-06-01 00:00:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('ends_at');
});

test('can replace banner image and delete it', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $banner = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id]);

    // attach
    $this->actingAs($user)->putJson("/api/link-pages/{$linkPage->slug}/banners/{$banner->id}", [
        'tmp_image' => fakeTmpBannerUpload(),
    ])->assertOk();

    expect($banner->fresh()->getMedia('image'))->toHaveCount(1);

    // delete
    $this->actingAs($user)->putJson("/api/link-pages/{$linkPage->slug}/banners/{$banner->id}", [
        'delete_image' => true,
    ])->assertOk();

    expect($banner->fresh()->getMedia('image'))->toHaveCount(0);
});

// ─── Reorder (GalleryManager contract: media_ids) ───────────────────

test('can reorder banners via media_ids', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $b1 = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id, 'sort_order' => 0]);
    $b2 = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id, 'sort_order' => 1]);

    $response = $this->actingAs($user)->postJson("/api/link-pages/{$linkPage->slug}/banners/reorder", [
        'media_ids' => [$b2->id, $b1->id],
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('link_page_banners', ['id' => $b2->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('link_page_banners', ['id' => $b1->id, 'sort_order' => 1]);
});

// ─── Bulk delete (GalleryManager contract: media_ids) ───────────────

test('can bulk delete banners via media_ids', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $b1 = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id]);
    $b2 = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id]);

    $response = $this->actingAs($user)->deleteJson("/api/link-pages/{$linkPage->slug}/banners/bulk-delete", [
        'media_ids' => [$b1->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('deleted_media.0.id', $b1->id);

    $this->assertSoftDeleted('link_page_banners', ['id' => $b1->id]);
    $this->assertDatabaseHas('link_page_banners', ['id' => $b2->id, 'deleted_at' => null]);
});

// ─── Toggle ─────────────────────────────────────────────────────────

test('can toggle banner active status', function () {
    $user = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $user->id]);
    $banner = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id, 'is_active' => true]);

    $response = $this->actingAs($user)->patchJson("/api/link-pages/{$linkPage->slug}/banners/{$banner->id}/toggle");

    $response->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// ─── Authorization ──────────────────────────────────────────────────

test('non-owner cannot create banners', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = createBannerUser(['link_pages.update']);
    $linkPage = LinkPage::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->postJson("/api/link-pages/{$linkPage->slug}/banners", [
        'tmp_images' => [fakeTmpBannerUpload()],
    ]);

    $response->assertForbidden();
    expect($linkPage->banners()->count())->toBe(0);
});

// ─── Resolve (public) ───────────────────────────────────────────────

test('resolve endpoint returns active banners ordered', function () {
    $user = User::factory()->create();
    $linkPage = LinkPage::factory()->create([
        'user_id' => $user->id,
        'slug' => 'banners-page',
        'is_active' => true,
    ]);

    LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id, 'sort_order' => 1, 'is_active' => true]);
    LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id, 'sort_order' => 0, 'is_active' => true]);
    LinkPageBanner::factory()->inactive()->create(['link_page_id' => $linkPage->id, 'sort_order' => 2]);

    $response = $this->getJson('/api/resolve/banners-page');

    $response->assertOk()
        ->assertJson(['type' => 'linkpage'])
        ->assertJsonCount(2, 'data.banners')
        ->assertJsonPath('data.banners.0.sort_order', 0);
});
