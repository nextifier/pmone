<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates posts with unique slugs via API when titles are duplicated', function () {
    // Create first post via API
    $response1 = $this->postJson('/api/posts', [
        'title' => 'Test Article',
        'content' => 'This is test content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
    ]);

    $response1->assertCreated();
    $post1 = Post::where('title', 'Test Article')->first();
    expect($post1->slug)->toBe('test-article');

    // Create second post with same title via API
    $response2 = $this->postJson('/api/posts', [
        'title' => 'Test Article',
        'content' => 'This is different content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
    ]);

    $response2->assertCreated();
    $post2 = Post::where('title', 'Test Article')->orderBy('id', 'desc')->first();

    // Should have unique slug with suffix
    expect($post2->slug)->not->toBe('test-article')
        ->and($post2->slug)->toStartWith('test-article-');

    // Verify both posts exist in database
    expect(Post::where('title', 'Test Article')->count())->toBe(2);
});

it('auto-fills published_at when creating post with published status via API', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Published Article',
        'content' => 'This is published content',
        'status' => 'published',
        'content_format' => 'html',
        'visibility' => 'public',
    ]);

    $response->assertCreated();

    $post = Post::where('title', 'Published Article')->first();
    expect($post->published_at)->not->toBeNull()
        ->and($post->published_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('uses custom slug from request when provided', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'My Great Article',
        'slug' => 'custom-slug',
        'content' => 'This is test content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
    ]);

    $response->assertCreated();

    $post = Post::where('title', 'My Great Article')->first();

    expect($post->slug)->toBe('custom-slug');
});

it('auto-generates slug when slug is empty', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'My Great Article',
        'slug' => '',
        'content' => 'This is test content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
    ]);

    $response->assertCreated();

    $post = Post::where('title', 'My Great Article')->first();

    expect($post->slug)->toBe('my-great-article');
});

it('updates post without changing slug when title remains same', function () {
    // Create post
    $post = Post::create([
        'title' => 'Original Title',
        'content' => 'Original content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $originalSlug = $post->slug;

    // Update post via API (without changing title)
    $response = $this->putJson("/api/posts/{$post->slug}", [
        'content' => 'Updated content',
        'status' => 'published',
    ]);

    $response->assertOk();

    $post->refresh();

    // Slug should remain the same
    expect($post->slug)->toBe($originalSlug)
        ->and($post->content)->toBe('Updated content');
});
