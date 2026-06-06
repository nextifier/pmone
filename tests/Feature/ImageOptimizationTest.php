<?php

use App\Models\Project;
use App\Models\ProjectBanner;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function tmpImageFile(string $ext, int $w, int $h): string
{
    $file = UploadedFile::fake()->image("x.{$ext}", $w, $h);
    $path = sys_get_temp_dir().'/io_'.uniqid().'.'.$ext;
    copy($file->getRealPath(), $path);

    return $path;
}

// ─── ImageOptimizer helper ──────────────────────────────────────────

test('downscales a large original in place and reports changed', function () {
    $path = tmpImageFile('jpg', 4000, 3000);

    expect(ImageOptimizer::compressInPlace($path, 1920, 82))->toBeTrue();

    [$w, $h] = getimagesize($path);
    expect(max($w, $h))->toBeLessThanOrEqual(1920);

    @unlink($path);
});

test('skips images already within the cap and small', function () {
    $path = tmpImageFile('jpg', 300, 300);

    expect(ImageOptimizer::compressInPlace($path, 1920, 82))->toBeFalse();

    @unlink($path);
});

test('skips non-raster files (svg)', function () {
    $path = sys_get_temp_dir().'/io_'.uniqid().'.svg';
    file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');

    expect(ImageOptimizer::compressInPlace($path))->toBeFalse();

    @unlink($path);
});

// ─── Upload chokepoint (tmp-upload) ─────────────────────────────────

test('tmp-upload downscales the stored original', function () {
    Storage::fake('local');
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->postJson('/api/tmp-upload', [
        'file' => UploadedFile::fake()->image('huge.jpg', 4000, 3000),
    ]);

    $response->assertOk();
    $folder = $response->json('folder');

    $path = Storage::disk('local')->path("tmp/uploads/{$folder}/huge.jpg");
    [$w, $h] = getimagesize($path);
    expect(max($w, $h))->toBeLessThanOrEqual(1920);
});

// ─── Backfill command ───────────────────────────────────────────────

test('media:compress-originals downscales large existing originals (idempotent)', function () {
    Storage::fake('public');
    Storage::fake('local');

    $banner = ProjectBanner::factory()->create([
        'project_id' => Project::factory(),
        'type' => 'image',
    ]);
    $banner->addMedia(tmpImageFile('jpg', 4000, 3000))->toMediaCollection('image');

    $media = $banner->getFirstMedia('image');
    $disk = Storage::disk($media->disk);
    $relative = $media->getPathRelativeToRoot();

    // Original is large before backfill.
    [$w0] = getimagesizefromstring($disk->get($relative));
    expect($w0)->toBeGreaterThan(1920);

    // Dry run: no change.
    $this->artisan('media:compress-originals --dry-run')->assertSuccessful();
    [$wDry] = getimagesizefromstring($disk->get($relative));
    expect($wDry)->toBe($w0);

    // Apply.
    $this->artisan('media:compress-originals --force')->assertSuccessful();
    [$w1, $h1] = getimagesizefromstring($disk->get($relative));
    expect(max($w1, $h1))->toBeLessThanOrEqual(1920);

    // Idempotent: second run leaves it as-is.
    $sizeAfter = $disk->size($relative);
    $this->artisan('media:compress-originals --force')->assertSuccessful();
    expect($disk->size($relative))->toBe($sizeAfter);
});
