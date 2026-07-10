<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    foreach (['brands.create', 'brands.read', 'brands.update', 'brands.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

/**
 * Create an active public API consumer and return its key.
 */
function publicApiKey(): string
{
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_brand_profile',
        'is_active' => true,
    ]);

    return 'pk_test_brand_profile';
}

/**
 * Push a file through /api/tmp-upload and return the tmp folder id.
 */
function uploadTmp($test, UploadedFile $file, bool $skipOptimize = false): string
{
    $payload = ['file' => $file];
    if ($skipOptimize) {
        $payload['skip_optimize'] = '1';
    }

    return $test->postJson('/api/tmp-upload', $payload)->assertOk()->json('folder');
}

test('a valid square profile image is stored in the profile_image collection', function () {
    $brand = Brand::factory()->create();
    $folder = uploadTmp($this, UploadedFile::fake()->image('avatar.jpg', 1200, 1200));

    $this->putJson("/api/brands/{$brand->slug}", ['tmp_profile_image' => $folder])
        ->assertSuccessful()
        ->assertJsonPath('data.profile_image.url', fn ($url) => is_string($url) && $url !== '');

    $brand->refresh();
    expect($brand->getMedia('profile_image'))->toHaveCount(1);
    expect($brand->profile_image)->toBeArray()->toHaveKey('sm');
});

test('a profile image smaller than 1000x1000 is rejected', function () {
    $brand = Brand::factory()->create();
    $folder = uploadTmp($this, UploadedFile::fake()->image('small.jpg', 500, 500));

    $this->putJson("/api/brands/{$brand->slug}", ['tmp_profile_image' => $folder])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tmp_profile_image');

    expect($brand->refresh()->getMedia('profile_image'))->toHaveCount(0);
});

test('an svg profile image is accepted regardless of pixel dimensions', function () {
    $brand = Brand::factory()->create();
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
    $folder = uploadTmp($this, UploadedFile::fake()->createWithContent('logo.svg', $svg));

    $this->putJson("/api/brands/{$brand->slug}", ['tmp_profile_image' => $folder])
        ->assertSuccessful();

    expect($brand->refresh()->getMedia('profile_image'))->toHaveCount(1);
});

test('a raw brand logo file is stored without conversions and exposes file metadata', function () {
    $brand = Brand::factory()->create();
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    $folder = uploadTmp($this, UploadedFile::fake()->createWithContent('master.pdf', $pdf));

    $this->putJson("/api/brands/{$brand->slug}", ['tmp_brand_logo' => $folder])
        ->assertSuccessful();

    $brand->refresh();
    $media = $brand->getFirstMedia('brand_logo');
    expect($media)->not->toBeNull();
    expect($media->getGeneratedConversions()->filter()->isEmpty())->toBeTrue();

    expect($brand->brand_logo)->toBeArray()
        ->toHaveKeys(['url', 'name', 'size', 'mime_type', 'extension', 'uploaded_at']);
    expect($brand->brand_logo['mime_type'])->toBe('application/pdf');
    expect($brand->brand_logo['extension'])->toBe('pdf');
});

test('skip_optimize keeps the original file untouched', function () {
    $folder = uploadTmp($this, UploadedFile::fake()->image('huge.jpg', 4000, 3000), skipOptimize: true);

    $path = Storage::disk('local')->path("tmp/uploads/{$folder}/huge.jpg");
    [$w, $h] = getimagesize($path);
    expect(max($w, $h))->toBe(4000);
});

test('the public brand resource exposes profile_image plus a brand_logo alias', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => true]);
    $brand = Brand::factory()->create(['status' => 'active', 'visibility' => 'public']);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);

    $brand->addMedia(UploadedFile::fake()->image('avatar.jpg', 1200, 1200))
        ->toMediaCollection('profile_image');

    $response = $this->withHeaders(['X-API-Key' => publicApiKey()])
        ->getJson("/api/public/projects/{$project->username}/brands")->assertSuccessful();

    $first = $response->json('data.0');
    expect($first['profile_image'])->not->toBeNull();
    expect($first['brand_logo'])->toEqual($first['profile_image']);
});

test('the public resource falls back to legacy brand_logo media before migration', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->published()->create(['project_id' => $project->id, 'is_active' => true]);
    $brand = Brand::factory()->create(['status' => 'active', 'visibility' => 'public']);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);

    // Legacy state: only brand_logo media exists, no profile_image yet.
    $brand->addMedia(UploadedFile::fake()->image('legacy.jpg', 1200, 1200))
        ->toMediaCollection('brand_logo');

    $first = $this->withHeaders(['X-API-Key' => publicApiKey()])
        ->getJson("/api/public/projects/{$project->username}/brands")
        ->assertSuccessful()
        ->json('data.0');

    expect($first['profile_image'])->not->toBeNull();
    expect($first['brand_logo'])->toEqual($first['profile_image']);
});

test('the copy command duplicates image logos into profile_image and is idempotent', function () {
    $brand = Brand::factory()->create();
    $brand->addMedia(UploadedFile::fake()->image('logo.jpg', 1200, 1200))
        ->toMediaCollection('brand_logo');

    // Dry run writes nothing.
    $this->artisan('brands:copy-logo-to-profile-image --dry-run')->assertSuccessful();
    expect($brand->refresh()->getMedia('profile_image'))->toHaveCount(0);

    // Apply copies once.
    $this->artisan('brands:copy-logo-to-profile-image --force')->assertSuccessful();
    expect($brand->refresh()->getMedia('profile_image'))->toHaveCount(1);

    // Second run is a no-op.
    $this->artisan('brands:copy-logo-to-profile-image --force')->assertSuccessful();
    expect($brand->refresh()->getMedia('profile_image'))->toHaveCount(1);
});

test('the copy command skips non-image master logos', function () {
    $brand = Brand::factory()->create();
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    $brand->addMedia(UploadedFile::fake()->createWithContent('master.pdf', $pdf))
        ->toMediaCollection('brand_logo');

    $this->artisan('brands:copy-logo-to-profile-image --force')->assertSuccessful();

    expect($brand->refresh()->getMedia('profile_image'))->toHaveCount(0);
});
