<?php

use App\Models\ApiConsumer;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('a published post dated in the future is normalized to scheduled', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Future post',
        'content' => '<p>Content</p>',
        'content_format' => 'html',
        'status' => 'published',
        'published_at' => now()->addDays(3)->toDateTimeString(),
    ])->assertSuccessful();

    expect($response->json('data.status'))->toBe('scheduled');
    $this->assertDatabaseHas('posts', ['slug' => $response->json('data.slug'), 'status' => 'scheduled']);
});

test('a published post backdated to the past stays published and is publicly visible', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_sched', 'is_active' => true]);

    $response = $this->postJson('/api/posts', [
        'title' => 'Backdated post',
        'content' => '<p>Content</p>',
        'content_format' => 'html',
        'status' => 'published',
        'visibility' => 'public',
        'published_at' => now()->subYear()->toDateTimeString(),
    ])->assertSuccessful();

    expect($response->json('data.status'))->toBe('published');

    $this->withHeaders(['X-API-Key' => 'pk_test_sched'])
        ->getJson('/api/public/blog/posts/'.$response->json('data.slug'))
        ->assertSuccessful();
});

test('a scheduled post is not publicly visible until its time', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_sched2', 'is_active' => true]);

    $post = Post::factory()->create([
        'status' => 'scheduled',
        'visibility' => 'public',
        'published_at' => now()->addDay(),
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_sched2'])
        ->getJson("/api/public/blog/posts/{$post->slug}")
        ->assertNotFound();
});

test('moving a published post to a future date reschedules it', function () {
    $post = Post::factory()->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->putJson("/api/posts/{$post->slug}", [
        'status' => 'published',
        'published_at' => now()->addWeek()->toDateTimeString(),
    ])->assertSuccessful();

    expect($response->json('data.status'))->toBe('scheduled');
});

test('a scheduled post with a past or missing date is normalized to published', function () {
    $past = Post::factory()->create([
        'status' => 'scheduled',
        'published_at' => now()->subHour(),
    ]);
    expect($past->fresh()->status)->toBe('published');

    $missing = Post::factory()->create([
        'status' => 'scheduled',
        'published_at' => null,
    ]);
    $missing = $missing->fresh();
    expect($missing->status)->toBe('published');
    expect($missing->published_at)->not->toBeNull();
});

test('posts:publish-scheduled still flips due scheduled posts to published', function () {
    $post = Post::factory()->create([
        'status' => 'scheduled',
        'published_at' => now()->addHour(),
    ]);
    expect($post->fresh()->status)->toBe('scheduled');

    $this->travel(2)->hours();

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe('published');
});
