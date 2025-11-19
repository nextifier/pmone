<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('automatically generates unique slugs for duplicate titles', function () {
    // Create first post with title "Test"
    $post1 = Post::create([
        'title' => 'Test',
        'content' => 'Test content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post1->slug)->toBe('test');

    // Create second post with same title "Test"
    $post2 = Post::create([
        'title' => 'Test',
        'content' => 'Test content 2',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    // Should have unique slug (test-1, test-2, etc)
    expect($post2->slug)->not->toBe('test')
        ->and($post2->slug)->toStartWith('test-');

    // Create third post with same title
    $post3 = Post::create([
        'title' => 'Test',
        'content' => 'Test content 3',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    // Should also have unique slug
    expect($post3->slug)->not->toBe('test')
        ->and($post3->slug)->not->toBe($post2->slug)
        ->and($post3->slug)->toStartWith('test-');
});

it('automatically sets published_at when creating post with published status', function () {
    $post = Post::create([
        'title' => 'Published Post',
        'content' => 'Published content',
        'status' => 'published',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post->published_at)->not->toBeNull()
        ->and($post->published_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('does not override published_at if already provided', function () {
    $customDate = now()->subDays(7);

    $post = Post::create([
        'title' => 'Published Post',
        'content' => 'Published content',
        'status' => 'published',
        'published_at' => $customDate,
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post->published_at->timestamp)->toBe($customDate->timestamp);
});

it('sets published_at to null for draft posts', function () {
    $post = Post::create([
        'title' => 'Draft Post',
        'content' => 'Draft content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post->published_at)->toBeNull();
});

it('automatically sets published_at when updating status to published', function () {
    $post = Post::create([
        'title' => 'Draft Post',
        'content' => 'Draft content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post->published_at)->toBeNull();

    // Update status to published
    $post->update(['status' => 'published']);

    expect($post->fresh()->published_at)->not->toBeNull()
        ->and($post->fresh()->published_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('does not override published_at when updating if already set', function () {
    $customDate = now()->subDays(7);

    $post = Post::create([
        'title' => 'Draft Post',
        'content' => 'Draft content',
        'status' => 'draft',
        'published_at' => $customDate,
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    // Update status to published
    $post->update(['status' => 'published']);

    expect($post->fresh()->published_at->timestamp)->toBe($customDate->timestamp);
});
