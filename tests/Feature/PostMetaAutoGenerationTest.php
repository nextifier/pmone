<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('meta auto-generation on create', function () {
    it('auto-generates meta_title from title when meta_title is empty', function () {
        $post = Post::create([
            'title' => 'My Amazing Post Title',
            'content' => 'Some content here',
            'status' => 'draft',
        ]);

        expect($post->meta_title)->toBe('My Amazing Post Title');
    });

    it('auto-generates meta_description from excerpt when meta_description is empty', function () {
        $post = Post::create([
            'title' => 'Test Post',
            'content' => 'Some content here',
            'excerpt' => 'This is the excerpt for the post',
            'status' => 'draft',
        ]);

        expect($post->meta_description)->toBe('This is the excerpt for the post');
    });

    it('limits meta_description to 160 characters from excerpt', function () {
        $longExcerpt = Str::repeat('Lorem ipsum dolor sit amet. ', 10);
        $post = Post::create([
            'title' => 'Test Post',
            'content' => 'Some content here',
            'excerpt' => $longExcerpt,
            'status' => 'draft',
        ]);

        expect(strlen($post->meta_description))->toBeLessThanOrEqual(163); // 160 + "..."
    });

    it('does not overwrite meta_title when provided', function () {
        $post = Post::create([
            'title' => 'My Post Title',
            'content' => 'Some content',
            'meta_title' => 'Custom SEO Title',
            'status' => 'draft',
        ]);

        expect($post->meta_title)->toBe('Custom SEO Title');
    });

    it('does not overwrite meta_description when provided', function () {
        $post = Post::create([
            'title' => 'Test Post',
            'content' => 'Some content',
            'excerpt' => 'This is the excerpt',
            'meta_description' => 'Custom SEO description here',
            'status' => 'draft',
        ]);

        expect($post->meta_description)->toBe('Custom SEO description here');
    });
});

describe('meta auto-generation on update', function () {
    it('auto-generates meta_title from title when meta_title is empty on update', function () {
        $post = Post::factory()->create([
            'meta_title' => 'Original Meta Title',
        ]);

        // Clear meta_title and update
        $post->update([
            'meta_title' => null,
            'title' => 'Updated Post Title',
        ]);

        expect($post->fresh()->meta_title)->toBe('Updated Post Title');
    });

    it('auto-generates meta_description from excerpt when meta_description is empty on update', function () {
        $post = Post::factory()->create([
            'meta_description' => 'Original Meta Description',
        ]);

        // Clear meta_description and update with new excerpt
        $post->update([
            'meta_description' => null,
            'excerpt' => 'New excerpt for the post',
        ]);

        expect($post->fresh()->meta_description)->toBe('New excerpt for the post');
    });

    it('auto-generates meta_title when clearing existing meta_title', function () {
        $post = Post::factory()->create([
            'title' => 'My Post Title',
            'meta_title' => 'Custom SEO Title',
        ]);

        // Clear meta_title
        $post->update(['meta_title' => '']);

        expect($post->fresh()->meta_title)->toBe('My Post Title');
    });

    it('auto-generates meta_description when clearing existing meta_description', function () {
        $post = Post::factory()->create([
            'excerpt' => 'This is my excerpt',
            'meta_description' => 'Custom SEO Description',
        ]);

        // Clear meta_description
        $post->update(['meta_description' => '']);

        expect($post->fresh()->meta_description)->toBe('This is my excerpt');
    });

    it('does not overwrite meta_title on update when already set', function () {
        $post = Post::factory()->create([
            'meta_title' => 'Custom SEO Title',
        ]);

        $post->update([
            'title' => 'New Title',
        ]);

        expect($post->fresh()->meta_title)->toBe('Custom SEO Title');
    });

    it('does not overwrite meta_description on update when already set', function () {
        $post = Post::factory()->create([
            'meta_description' => 'Custom SEO Description',
        ]);

        $post->update([
            'excerpt' => 'New excerpt',
        ]);

        expect($post->fresh()->meta_description)->toBe('Custom SEO Description');
    });

    it('limits meta_description to 160 characters from excerpt on update', function () {
        $post = Post::factory()->create([
            'meta_description' => null,
        ]);

        $longExcerpt = Str::repeat('Lorem ipsum dolor sit amet. ', 10);
        $post->update([
            'excerpt' => $longExcerpt,
            'meta_description' => null,
        ]);

        expect(strlen($post->fresh()->meta_description))->toBeLessThanOrEqual(163);
    });
});
