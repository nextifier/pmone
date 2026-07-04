<?php

use App\Jobs\GeneratePostOgImage;
use App\Models\Post;
use App\Models\User;
use App\Services\Og\OgScreenshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function seedTmpFeaturedUpload(string $folder): void
{
    $image = imagecreatetruecolor(1600, 900);
    ob_start();
    imagejpeg($image, null, 90);
    $contents = ob_get_clean();
    imagedestroy($image);

    Storage::disk('local')->put("tmp/uploads/{$folder}/featured.jpg", $contents);
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => 'featured.jpg',
    ]));
}

test('creating a post with a featured image dispatches generation', function () {
    Queue::fake();
    seedTmpFeaturedUpload('tmp-genstore');

    $this->postJson('/api/posts', [
        'title' => 'Post with image',
        'content' => '<p>Content</p>',
        'content_format' => 'html',
        'status' => 'draft',
        'tmp_featured_image' => 'tmp-genstore',
    ])->assertSuccessful();

    Queue::assertPushedOn('pdf-batch', GeneratePostOgImage::class);
});

test('creating a post without a featured image does not dispatch generation', function () {
    Queue::fake();

    $this->postJson('/api/posts', [
        'title' => 'Plain post',
        'content' => '<p>Content</p>',
        'content_format' => 'html',
        'status' => 'draft',
    ])->assertSuccessful();

    Queue::assertNotPushed(GeneratePostOgImage::class);
});

test('changing the title dispatches regeneration', function () {
    Queue::fake();
    $post = Post::factory()->create(['title' => ['en' => 'Old title']]);

    $this->putJson("/api/posts/{$post->slug}", [
        'title' => 'New title',
    ])->assertSuccessful();

    Queue::assertPushed(GeneratePostOgImage::class);
});

test('an update touching neither title nor featured image does not dispatch', function () {
    Queue::fake();
    $post = Post::factory()->create(['title' => ['en' => 'Same title']]);

    $this->putJson("/api/posts/{$post->slug}", [
        'title' => 'Same title',
        'excerpt' => 'New excerpt only',
    ])->assertSuccessful();

    Queue::assertNotPushed(GeneratePostOgImage::class);
});

test('replacing the featured image dispatches regeneration', function () {
    Queue::fake();
    seedTmpFeaturedUpload('tmp-genupdate');
    $post = Post::factory()->create();

    $this->putJson("/api/posts/{$post->slug}", [
        'tmp_featured_image' => 'tmp-genupdate',
    ])->assertSuccessful();

    Queue::assertPushed(GeneratePostOgImage::class);
});

test('deleting the featured image clears the generated OG image', function () {
    Queue::fake();
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('generated.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');

    $this->putJson("/api/posts/{$post->slug}", [
        'delete_featured_image' => true,
    ])->assertSuccessful();

    expect($post->fresh()->hasMedia('og_image_generated'))->toBeFalse();
    Queue::assertPushed(GeneratePostOgImage::class);
});

test('the job renders the card with the post title and stores it with a source hash', function () {
    $post = Post::factory()->create(['title' => ['en' => 'A remarkable headline']]);
    $post->addMedia(UploadedFile::fake()->image('featured.jpg', 1600, 900))
        ->toMediaCollection('featured_image');

    $fake = app(OgScreenshotService::class);

    (new GeneratePostOgImage($post->id))->handle($fake);

    expect($fake->capturedHtml)->toHaveCount(1);
    expect($fake->capturedHtml[0])->toContain('A remarkable headline');
    expect($fake->capturedHtml[0])->toContain('data:image/');

    $media = $post->fresh()->getFirstMedia('og_image_generated');
    expect($media)->not->toBeNull();
    expect($media->getCustomProperty('source_hash'))->not->toBeEmpty();
    expect($media->getCustomProperty('width'))->toBe(1200);
});

test('the job is idempotent for an unchanged title and featured image', function () {
    $post = Post::factory()->create(['title' => ['en' => 'Stable title']]);
    $post->addMedia(UploadedFile::fake()->image('featured.jpg', 1600, 900))
        ->toMediaCollection('featured_image');

    $fake = app(OgScreenshotService::class);

    (new GeneratePostOgImage($post->id))->handle($fake);
    $firstId = $post->fresh()->getFirstMedia('og_image_generated')->id;

    (new GeneratePostOgImage($post->id))->handle($fake);

    expect($fake->capturedHtml)->toHaveCount(1);
    expect($post->fresh()->getFirstMedia('og_image_generated')->id)->toBe($firstId);
});

test('force bypasses the idempotency hash and replaces the card', function () {
    $post = Post::factory()->create(['title' => ['en' => 'Same title']]);
    $post->addMedia(UploadedFile::fake()->image('featured.jpg', 1600, 900))
        ->toMediaCollection('featured_image');

    $fake = app(OgScreenshotService::class);

    (new GeneratePostOgImage($post->id))->handle($fake);
    $firstId = $post->fresh()->getFirstMedia('og_image_generated')->id;

    (new GeneratePostOgImage($post->id, force: true))->handle($fake);

    expect($fake->capturedHtml)->toHaveCount(2);
    expect($post->fresh()->getFirstMedia('og_image_generated')->id)->not->toBe($firstId);
});

test('a changed title regenerates the card', function () {
    $post = Post::factory()->create(['title' => ['en' => 'First title']]);
    $post->addMedia(UploadedFile::fake()->image('featured.jpg', 1600, 900))
        ->toMediaCollection('featured_image');

    $fake = app(OgScreenshotService::class);

    (new GeneratePostOgImage($post->id))->handle($fake);
    $firstId = $post->fresh()->getFirstMedia('og_image_generated')->id;

    $post->update(['title' => ['en' => 'Second title']]);
    (new GeneratePostOgImage($post->id))->handle($fake);

    $media = $post->fresh()->getFirstMedia('og_image_generated');
    expect($media->id)->not->toBe($firstId);
    expect($fake->capturedHtml[1])->toContain('Second title');
});

test('media urls with spaces and special characters are encoded for the http fallback', function () {
    expect(GeneratePostOgImage::encodeMediaUrl(
        'https://cdn.pmone.id/posts/featured_image/636/conversions/Screenshot-2026-03-10-at-2.43.05 PM-lg.jpg'
    ))->toBe('https://cdn.pmone.id/posts/featured_image/636/conversions/Screenshot-2026-03-10-at-2.43.05%20PM-lg.jpg');

    expect(GeneratePostOgImage::encodeMediaUrl('https://cdn.pmone.id/img/kopi (1).jpg?v=2'))
        ->toBe('https://cdn.pmone.id/img/kopi%20(1).jpg?v=2');

    expect(GeneratePostOgImage::encodeMediaUrl('https://cdn.pmone.id/img/plain.jpg'))
        ->toBe('https://cdn.pmone.id/img/plain.jpg');

    // macOS screenshot names use a narrow no-break space (U+202F) before AM/PM
    expect(GeneratePostOgImage::encodeMediaUrl(
        "https://cdn.pmone.id/posts/featured_image/636/conversions/Screenshot-at-2.43.05\u{202F}PM-lg.jpg"
    ))->toBe('https://cdn.pmone.id/posts/featured_image/636/conversions/Screenshot-at-2.43.05%E2%80%AFPM-lg.jpg');
});

test('the job clears the generated image when the featured image is gone', function () {
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('generated.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');

    (new GeneratePostOgImage($post->id))->handle(app(OgScreenshotService::class));

    expect($post->fresh()->hasMedia('og_image_generated'))->toBeFalse();
});
