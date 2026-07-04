<?php

use App\Jobs\GeneratePostOgImage;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function postWithFeatured(array $attributes = []): Post
{
    $post = Post::factory()->create($attributes);
    $post->addMedia(UploadedFile::fake()->image('featured.jpg', 1600, 900))
        ->toMediaCollection('featured_image');

    return $post;
}

beforeEach(function () {
    Queue::fake();
});

test('dispatches only posts with a featured image and no generated card', function () {
    $missing = postWithFeatured();

    $alreadyGenerated = postWithFeatured();
    $alreadyGenerated->addMedia(UploadedFile::fake()->image('og.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');

    $noFeatured = Post::factory()->create();

    $this->artisan('posts:generate-og-images', ['--force' => true])
        ->assertSuccessful();

    Queue::assertPushed(GeneratePostOgImage::class, 1);
    Queue::assertPushed(GeneratePostOgImage::class, fn ($job) => $job->postId === $missing->id);
});

test('--all also dispatches posts that already have a generated card', function () {
    postWithFeatured();

    $alreadyGenerated = postWithFeatured();
    $alreadyGenerated->addMedia(UploadedFile::fake()->image('og.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');

    $this->artisan('posts:generate-og-images', ['--all' => true, '--force' => true])
        ->assertSuccessful();

    Queue::assertPushed(GeneratePostOgImage::class, 2);
});

test('--limit caps the number of dispatched jobs', function () {
    postWithFeatured();
    postWithFeatured();
    postWithFeatured();

    $this->artisan('posts:generate-og-images', ['--limit' => 2, '--force' => true])
        ->assertSuccessful();

    Queue::assertPushed(GeneratePostOgImage::class, 2);
});

test('--dry-run dispatches nothing', function () {
    postWithFeatured();

    $this->artisan('posts:generate-og-images', ['--dry-run' => true])
        ->assertSuccessful();

    Queue::assertNotPushed(GeneratePostOgImage::class);
});

test('jobs are staggered by the delay option', function () {
    postWithFeatured();
    postWithFeatured();
    postWithFeatured();

    $this->artisan('posts:generate-og-images', ['--force' => true, '--delay' => 6])
        ->assertSuccessful();

    $delays = [];
    Queue::assertPushed(GeneratePostOgImage::class, function ($job) use (&$delays) {
        $delays[] = $job->delay;

        return true;
    });

    expect($delays)->toHaveCount(3);
    expect($delays[0])->toBeNull();
    expect($delays[1])->not->toBeNull();
    expect($delays[2]->greaterThan($delays[1]))->toBeTrue();
});

test('a declined confirmation dispatches nothing', function () {
    postWithFeatured();

    $this->artisan('posts:generate-og-images')
        ->expectsConfirmation('Dispatch these jobs?', 'no')
        ->assertSuccessful();

    Queue::assertNotPushed(GeneratePostOgImage::class);
});

test('reports when nothing needs generating', function () {
    Post::factory()->create();

    $this->artisan('posts:generate-og-images', ['--force' => true])
        ->expectsOutputToContain('already has a generated OG card')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
