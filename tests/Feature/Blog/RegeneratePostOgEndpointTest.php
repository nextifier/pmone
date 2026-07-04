<?php

use App\Jobs\GeneratePostOgImage;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $this->master = User::factory()->create(['email_verified_at' => now()]);
    $this->master->assignRole('master');
});

function featuredPost(): Post
{
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('featured.jpg', 1600, 900))
        ->toMediaCollection('featured_image');

    return $post;
}

test('a master can queue a forced regeneration for one post', function () {
    $post = featuredPost();

    $this->actingAs($this->master)
        ->postJson("/api/posts/{$post->slug}/regenerate-og")
        ->assertSuccessful();

    Queue::assertPushed(GeneratePostOgImage::class, fn ($job) => $job->postId === $post->id && $job->force === true);
});

test('non-master users get 403 on single regeneration', function () {
    $post = featuredPost();

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->postJson("/api/posts/{$post->slug}/regenerate-og")
        ->assertForbidden();

    Queue::assertNotPushed(GeneratePostOgImage::class);
});

test('a post without a featured image returns 422', function () {
    $post = Post::factory()->create();

    $this->actingAs($this->master)
        ->postJson("/api/posts/{$post->slug}/regenerate-og")
        ->assertUnprocessable();

    Queue::assertNotPushed(GeneratePostOgImage::class);
});

test('bulk regeneration queues a forced job for every post with a featured image', function () {
    featuredPost();
    featuredPost();
    Post::factory()->create();

    $this->actingAs($this->master)
        ->postJson('/api/posts/bulk/regenerate-og')
        ->assertSuccessful()
        ->assertJsonPath('dispatched', 2);

    Queue::assertPushed(GeneratePostOgImage::class, 2);
    Queue::assertPushed(GeneratePostOgImage::class, fn ($job) => $job->force === true);
});

test('non-master users get 403 on bulk regeneration', function () {
    featuredPost();

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->postJson('/api/posts/bulk/regenerate-og')
        ->assertForbidden();

    Queue::assertNotPushed(GeneratePostOgImage::class);
});
