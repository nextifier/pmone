<?php

use App\Models\ApiConsumer;
use App\Models\Project;
use App\Models\ProjectBanner;
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

/** A user who is a member of a fresh project (passes ProjectPolicy view/update). */
function projectBannerMember(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    $project = Project::factory()->create();
    $project->members()->attach($user->id);

    return [$project, $user];
}

/** Simulate a completed temporary upload and return its folder id. */
function fakeTmpProjectBannerUpload(string $name = 'banner.jpg'): string
{
    $folder = uniqid('tmp-', true);
    $file = UploadedFile::fake()->image($name, 1920, 480);

    Storage::disk('local')->putFileAs("tmp/uploads/{$folder}", $file, $name);
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => $name,
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'uploaded_at' => now()->toISOString(),
    ]));

    return $folder;
}

function attachProjectBannerImage(ProjectBanner $banner, int $w = 1920, int $h = 480): void
{
    $file = UploadedFile::fake()->image('banner.jpg', $w, $h);
    $banner->addMedia($file->getRealPath())
        ->usingFileName('banner.jpg')
        ->withCustomProperties(['width' => $w, 'height' => $h])
        ->toMediaCollection('image');
}

/** Simulate a completed TipTap temp-media upload and return its folder id. */
function fakeTmpMediaUpload(string $name = 'content.jpg'): string
{
    $folder = uniqid('tmp-media-', true);
    $file = UploadedFile::fake()->image($name, 800, 400);

    Storage::disk('local')->putFileAs("tmp/uploads/{$folder}", $file, $name);
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => $name,
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'uploaded_at' => now()->toISOString(),
    ]));

    return $folder;
}

// ─── Store ──────────────────────────────────────────────────────────

test('can create an image banner', function () {
    [$project, $user] = projectBannerMember();

    $response = $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'image',
        'placement' => 'hero',
        'aspect_ratio' => '4:1',
        'tmp_image' => fakeTmpProjectBannerUpload(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'image')
        ->assertJsonPath('data.sort_order', 0);

    $banner = $project->banners()->first();
    expect($banner->getMedia('image'))->toHaveCount(1);
});

test('can create a text banner without image', function () {
    [$project, $user] = projectBannerMember();

    $response = $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'title' => 'Exhibit at Megabuild Indonesia!',
        'description' => '<p>Build success together.</p>',
        'cta_label' => 'Book Your Space Now',
        'link' => '/book-space',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'text')
        ->assertJsonPath('data.title', 'Exhibit at Megabuild Indonesia!');

    $banner = $project->banners()->first();
    expect($banner->getMedia('image'))->toHaveCount(0);
    expect($banner->cta_label)->toBe('Book Your Space Now');
});

test('can create an image_text banner', function () {
    [$project, $user] = projectBannerMember();

    $response = $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'image_text',
        'title' => 'Stay Inspired',
        'description' => '<p>Follow us.</p>',
        'cta_label' => 'Follow',
        'link' => 'https://instagram.com',
        'tmp_image' => fakeTmpProjectBannerUpload(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'image_text');

    expect($project->banners()->first()->getMedia('image'))->toHaveCount(1);
});

test('image banner requires an image', function () {
    [$project, $user] = projectBannerMember();

    $response = $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'image',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('tmp_image');
});

test('rejects invalid type and aspect_ratio', function () {
    [$project, $user] = projectBannerMember();

    $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'video',
    ])->assertJsonValidationErrors('type');

    $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'aspect_ratio' => '3:2',
    ])->assertJsonValidationErrors('aspect_ratio');
});

test('rejects end time before start time', function () {
    [$project, $user] = projectBannerMember();

    $response = $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'start_time' => '2026-06-30 00:00:00',
        'end_time' => '2026-06-01 00:00:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('end_time');
});

test('store appends sort_order after existing banners', function () {
    [$project, $user] = projectBannerMember();
    ProjectBanner::factory()->create(['project_id' => $project->id, 'sort_order' => 5]);

    $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'title' => 'Next',
    ])->assertCreated();

    expect($project->banners()->max('sort_order'))->toBe(6);
});

test('persists the placement chosen on the form', function () {
    [$project, $user] = projectBannerMember();

    $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'title' => 'Main CTA banner',
        'placement' => 'main_cta',
    ])->assertCreated()
        ->assertJsonPath('data.placement', 'main_cta');

    $this->assertDatabaseHas('project_banners', [
        'project_id' => $project->id,
        'placement' => 'main_cta',
    ]);
});

test('defaults placement to hero when omitted', function () {
    [$project, $user] = projectBannerMember();

    $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'title' => 'No placement given',
    ])->assertCreated();

    expect($project->banners()->first()->placement)->toBe('hero');
});

// ─── Update ─────────────────────────────────────────────────────────

test('can update banner metadata', function () {
    [$project, $user] = projectBannerMember();
    $banner = ProjectBanner::factory()->text()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->putJson("/api/projects/{$project->username}/banners/{$banner->id}", [
        'title' => 'Updated title',
        'is_active' => false,
        'start_time' => '2026-06-01 00:00:00',
        'end_time' => '2026-06-30 23:59:59',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('project_banners', [
        'id' => $banner->id,
        'title' => 'Updated title',
    ]);
});

test('can replace and delete banner image', function () {
    [$project, $user] = projectBannerMember();
    $banner = ProjectBanner::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->putJson("/api/projects/{$project->username}/banners/{$banner->id}", [
        'tmp_image' => fakeTmpProjectBannerUpload(),
    ])->assertOk();

    expect($banner->fresh()->getMedia('image'))->toHaveCount(1);

    $this->actingAs($user)->putJson("/api/projects/{$project->username}/banners/{$banner->id}", [
        'delete_image' => true,
    ])->assertOk();

    expect($banner->fresh()->getMedia('image'))->toHaveCount(0);
});

// ─── Reorder / bulk-delete / toggle ─────────────────────────────────

test('can reorder banners via media_ids', function () {
    [$project, $user] = projectBannerMember();
    $b1 = ProjectBanner::factory()->create(['project_id' => $project->id, 'sort_order' => 0]);
    $b2 = ProjectBanner::factory()->create(['project_id' => $project->id, 'sort_order' => 1]);

    $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners/reorder", [
        'media_ids' => [$b2->id, $b1->id],
    ])->assertOk();

    $this->assertDatabaseHas('project_banners', ['id' => $b2->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('project_banners', ['id' => $b1->id, 'sort_order' => 1]);
});

test('can bulk delete banners', function () {
    [$project, $user] = projectBannerMember();
    $b1 = ProjectBanner::factory()->create(['project_id' => $project->id]);
    $b2 = ProjectBanner::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->deleteJson("/api/projects/{$project->username}/banners/bulk-delete", [
        'media_ids' => [$b1->id],
    ])->assertOk()
        ->assertJsonPath('deleted_media.0.id', $b1->id);

    $this->assertSoftDeleted('project_banners', ['id' => $b1->id]);
    $this->assertDatabaseHas('project_banners', ['id' => $b2->id, 'deleted_at' => null]);
});

test('can toggle banner active status', function () {
    [$project, $user] = projectBannerMember();
    $banner = ProjectBanner::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    $this->actingAs($user)->patchJson("/api/projects/{$project->username}/banners/{$banner->id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// ─── Authorization / scope ──────────────────────────────────────────

test('non-member cannot create banners', function () {
    [$project] = projectBannerMember();
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('user');

    $this->actingAs($outsider)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'title' => 'Nope',
    ])->assertForbidden();

    expect($project->banners()->count())->toBe(0);
});

test('cannot update a banner from another project', function () {
    [$projectA, $user] = projectBannerMember();
    $projectB = Project::factory()->create();
    $foreign = ProjectBanner::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs($user)->putJson("/api/projects/{$projectA->username}/banners/{$foreign->id}", [
        'title' => 'Hijack',
    ])->assertNotFound();
});

// ─── Public endpoint ────────────────────────────────────────────────

test('public banners endpoint requires api key', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/public/banners?project_slug={$project->username}")
        ->assertStatus(401);
});

test('public banners endpoint returns mapped active banners filtered by placement', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_123456789',
        'is_active' => true,
    ]);
    $headers = ['X-API-Key' => 'pk_test_123456789'];

    $project = Project::factory()->create();

    $imageBanner = ProjectBanner::factory()->create([
        'project_id' => $project->id,
        'type' => 'image',
        'placement' => 'hero',
        'aspect_ratio' => '4:1',
        'title' => 'Seyven',
        'sort_order' => 0,
        'start_time' => '2026-05-25 00:00:00',
    ]);
    attachProjectBannerImage($imageBanner);

    ProjectBanner::factory()->text()->create([
        'project_id' => $project->id,
        'placement' => 'hero',
        'title' => 'Exhibit now',
        'cta_label' => 'Book',
        'link' => '/book-space',
        'sort_order' => 1,
    ]);

    // Excluded: inactive + different placement
    ProjectBanner::factory()->inactive()->create(['project_id' => $project->id, 'placement' => 'hero']);
    ProjectBanner::factory()->create(['project_id' => $project->id, 'placement' => 'sidebar']);

    $response = $this->withHeaders($headers)
        ->getJson("/api/public/banners?project_slug={$project->username}&placement=hero");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        // image banner (sort_order 0)
        ->assertJsonPath('data.0.adImage.alt', 'Seyven')
        ->assertJsonPath('data.0.aspectRatio', '4:1')
        // text banner (sort_order 1)
        ->assertJsonPath('data.1.subHeadline', 'Exhibit now')
        ->assertJsonPath('data.1.cta.label', 'Book')
        ->assertJsonPath('data.1.cta.link', '/book-space');

    expect($response->json('data.0.adImage.srcFull'))->not->toBeNull();
    expect($response->json('data.0.adImage.srcset'))->toContain('w'); // native <img srcset>
    expect($response->json('data.0.startTime'))->not->toBeNull();
    // text banner must not carry an adImage key
    expect($response->json('data.1'))->not->toHaveKey('adImage');
});

test('public banners endpoint does not time-filter future banners', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_123456789', 'is_active' => true]);

    $project = Project::factory()->create();
    ProjectBanner::factory()->text()->create([
        'project_id' => $project->id,
        'placement' => 'hero',
        'start_time' => now()->addYear(),
        'end_time' => now()->addYears(2),
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_123456789'])
        ->getJson("/api/public/banners?project_slug={$project->username}&placement=hero")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('public banner resource includes id for tracking', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_123456789', 'is_active' => true]);
    $project = Project::factory()->create();
    $banner = ProjectBanner::factory()->text()->create([
        'project_id' => $project->id,
        'placement' => 'hero',
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_123456789'])
        ->getJson("/api/public/banners?project_slug={$project->username}&placement=hero")
        ->assertOk()
        ->assertJsonPath('data.0.id', $banner->id);
});

// ─── Tracking + analytics ───────────────────────────────────────────

test('admin list exposes click and impression counts', function () {
    [$project, $user] = projectBannerMember();
    $banner = ProjectBanner::factory()->create(['project_id' => $project->id]);
    $banner->clicks()->create(['clicked_at' => now()]);
    $banner->impressions()->create(['visited_at' => now()]);

    $this->actingAs($user)->getJson("/api/projects/{$project->username}/banners")
        ->assertOk()
        ->assertJsonPath('data.0.clicks_count', 1)
        ->assertJsonPath('data.0.impressions_count', 1);
});

test('tracking endpoints record banner clicks and impressions', function () {
    $banner = ProjectBanner::factory()->create();

    $this->postJson('/api/track/click', [
        'clickable_type' => 'App\\Models\\ProjectBanner',
        'clickable_id' => $banner->id,
        'link_label' => 'hero',
    ])->assertCreated();

    $this->postJson('/api/track/visit', [
        'visitable_type' => 'App\\Models\\ProjectBanner',
        'visitable_id' => $banner->id,
    ])->assertCreated();

    expect($banner->clicks()->count())->toBe(1);
    expect($banner->impressions()->count())->toBe(1);
});

test('banner analytics returns summary, ctr and per_day', function () {
    [$project, $user] = projectBannerMember();
    $banner = ProjectBanner::factory()->create(['project_id' => $project->id]);
    $banner->impressions()->create(['visited_at' => now()]);
    $banner->impressions()->create(['visited_at' => now()]);
    $banner->clicks()->create(['clicked_at' => now()]);

    $response = $this->actingAs($user)
        ->getJson("/api/projects/{$project->username}/banners/{$banner->id}/analytics");

    $response->assertOk()
        ->assertJsonPath('data.summary.impressions', 2)
        ->assertJsonPath('data.summary.clicks', 1)
        ->assertJsonPath('data.summary.ctr', 50);
    expect($response->json('data.per_day'))->toHaveCount(14);
});

test('banner analytics is scoped to the project', function () {
    [$projectA, $user] = projectBannerMember();
    $projectB = Project::factory()->create();
    $foreign = ProjectBanner::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs($user)
        ->getJson("/api/projects/{$projectA->username}/banners/{$foreign->id}/analytics")
        ->assertNotFound();
});

test('store moves tiptap content images to permanent media', function () {
    [$project, $user] = projectBannerMember();
    $folder = fakeTmpMediaUpload();

    $this->actingAs($user)->postJson("/api/projects/{$project->username}/banners", [
        'type' => 'text',
        'title' => 'Rich',
        'description' => '<p>Hi</p><img src="/api/tmp-media/'.$folder.'">',
    ])->assertCreated();

    $banner = $project->banners()->first();
    expect($banner->description)->not->toContain('/api/tmp-media/');
    expect($banner->getMedia('description_images'))->toHaveCount(1);
});
