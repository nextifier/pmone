<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Create Post Tests
test('user can create a post', function () {
    $postData = [
        'title' => 'Test Post Title',
        'slug' => 'test-post-title',
        'excerpt' => 'Test excerpt',
        'content' => '<p>Test content</p>',
        'content_format' => 'html',
        'status' => 'draft',
        'visibility' => 'public',
        'featured' => false,
    ];

    $response = $this->postJson('/api/posts', $postData);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'title',
                'slug',
                'content',
                'status',
            ],
        ]);

    $this->assertDatabaseHas('posts', [
        'title' => 'Test Post Title',
        'slug' => 'test-post-title',
        'status' => 'draft',
    ]);
});

test('user can create post with authors and categories', function () {
    $author1 = User::factory()->create();
    $author2 = User::factory()->create();
    $category1 = Category::factory()->create();
    $category2 = Category::factory()->create();

    $postData = [
        'title' => 'Post with Relations',
        'content' => '<p>Content</p>',
        'content_format' => 'html',
        'status' => 'draft',
        'authors' => [
            [
                'user_id' => $author1->id,
                'role' => 'primary_author',
                'order' => 0,
            ],
            [
                'user_id' => $author2->id,
                'role' => 'co_author',
                'order' => 1,
            ],
        ],
        'category_ids' => [$category1->id, $category2->id],
    ];

    $response = $this->postJson('/api/posts', $postData);

    $response->assertSuccessful();

    $post = Post::where('title', 'Post with Relations')->first();

    expect($post->authors)->toHaveCount(2);
    expect($post->postCategories)->toHaveCount(2);

    // Check pivot data
    $primaryAuthor = $post->authors()->wherePivot('order', 0)->first();
    expect($primaryAuthor->id)->toBe($author1->id);
    expect($primaryAuthor->pivot->order)->toBe(0);
});

test('post title is required', function () {
    $response = $this->postJson('/api/posts', [
        'content' => '<p>Content</p>',
        'status' => 'draft',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

test('post content is required', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Title',
        'status' => 'draft',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

// Update Post Tests
test('user can update a post', function () {
    $post = Post::factory()->create([
        'title' => 'Original Title',
        'created_by' => $this->user->id,
    ]);

    $response = $this->putJson("/api/posts/{$post->slug}", [
        'title' => 'Updated Title',
        'content' => '<p>Updated content</p>',
        'status' => 'published',
    ]);

    $response->assertSuccessful();

    $post->refresh();
    expect($post->title)->toBe('Updated Title');
    expect($post->status)->toBe('published');
});

test('user can update post authors and categories', function () {
    $post = Post::factory()->create(['created_by' => $this->user->id]);

    $author1 = User::factory()->create();
    $author2 = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->putJson("/api/posts/{$post->slug}", [
        'title' => $post->title,
        'content' => $post->content,
        'authors' => [
            [
                'user_id' => $author1->id,
                'role' => 'primary_author',
                'order' => 0,
            ],
            [
                'user_id' => $author2->id,
                'role' => 'contributor',
                'order' => 1,
            ],
        ],
        'category_ids' => [$category->id],
    ]);

    $response->assertSuccessful();

    $post->refresh();
    expect($post->authors)->toHaveCount(2);
    expect($post->postCategories)->toHaveCount(1);
});

// Delete & Trash Tests
test('user can soft delete a post', function () {
    $post = Post::factory()->create(['created_by' => $this->user->id]);

    $response = $this->deleteJson("/api/posts/{$post->slug}");

    $response->assertSuccessful();

    $this->assertSoftDeleted('posts', ['id' => $post->id]);
});

test('user can view trashed posts', function () {
    Post::factory()->count(3)->create(['created_by' => $this->user->id]);
    $trashedPost = Post::factory()->create(['created_by' => $this->user->id]);
    $trashedPost->delete();

    $response = $this->getJson('/api/posts/trash');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('user can restore a trashed post', function () {
    $post = Post::factory()->create(['created_by' => $this->user->id]);
    $post->delete();

    $response = $this->postJson("/api/posts/trash/{$post->id}/restore");

    $response->assertSuccessful();

    $post->refresh();
    expect($post->deleted_at)->toBeNull();
});

test('user can permanently delete a post', function () {
    // forceDelete requires master or admin role
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->user->assignRole('admin');

    $post = Post::factory()->create(['created_by' => $this->user->id]);
    $post->delete();

    $response = $this->deleteJson("/api/posts/trash/{$post->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

test('permanently deleting a post deletes all images', function () {
    // forceDelete requires master or admin role
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->user->assignRole('admin');

    Storage::fake('public');

    $post = Post::factory()->create(['created_by' => $this->user->id]);

    // Add featured image
    $featuredImage = UploadedFile::fake()->image('featured.jpg');
    $post->addMedia($featuredImage)->toMediaCollection('featured_image');

    // Add og image
    $ogImage = UploadedFile::fake()->image('og.jpg');
    $post->addMedia($ogImage)->toMediaCollection('og_image');

    // Add content images
    $contentImage1 = UploadedFile::fake()->image('content1.jpg');
    $post->addMedia($contentImage1)->toMediaCollection('content_images');

    $contentImage2 = UploadedFile::fake()->image('content2.jpg');
    $post->addMedia($contentImage2)->toMediaCollection('content_images');

    // Verify all images are added
    expect($post->getMedia('featured_image'))->toHaveCount(1);
    expect($post->getMedia('og_image'))->toHaveCount(1);
    expect($post->getMedia('content_images'))->toHaveCount(2);

    $mediaCount = $post->media()->count();
    expect($mediaCount)->toBe(4);

    // Soft delete first, then permanently delete
    $post->delete();
    $response = $this->deleteJson("/api/posts/trash/{$post->id}");

    $response->assertSuccessful();

    // Verify post is deleted from database
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);

    // Verify all media records are deleted from the database
    $this->assertDatabaseMissing('media', ['model_type' => Post::class, 'model_id' => $post->id]);
});

// Bulk Operations Tests
test('user can bulk delete posts', function () {
    $posts = Post::factory()->count(3)->create(['created_by' => $this->user->id]);
    $postIds = $posts->pluck('id')->toArray();

    $response = $this->deleteJson('/api/posts/bulk', [
        'ids' => $postIds,
    ]);

    $response->assertSuccessful();

    foreach ($postIds as $id) {
        $this->assertSoftDeleted('posts', ['id' => $id]);
    }
});

test('user can bulk update post status', function () {
    $posts = Post::factory()->count(3)->create([
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
    $postIds = $posts->pluck('id')->toArray();

    $response = $this->postJson('/api/posts/bulk/status', [
        'ids' => $postIds,
        'status' => 'published',
    ]);

    $response->assertSuccessful();

    foreach ($postIds as $id) {
        $this->assertDatabaseHas('posts', [
            'id' => $id,
            'status' => 'published',
        ]);
    }
});

test('user can bulk restore posts', function () {
    $posts = Post::factory()->count(3)->create(['created_by' => $this->user->id]);

    foreach ($posts as $post) {
        $post->delete();
    }

    $postIds = $posts->pluck('id')->toArray();

    $response = $this->postJson('/api/posts/trash/restore/bulk', [
        'ids' => $postIds,
    ]);

    $response->assertSuccessful();

    foreach ($postIds as $id) {
        $post = Post::find($id);
        expect($post->deleted_at)->toBeNull();
    }
});

// Validation Tests
test('post status must be valid', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post',
        'content' => '<p>Content</p>',
        'status' => 'invalid_status',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('post visibility must be valid', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post',
        'content' => '<p>Content</p>',
        'visibility' => 'invalid_visibility',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['visibility']);
});

test('author user_id must be valid', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post',
        'content' => '<p>Content</p>',
        'authors' => [
            [
                'user_id' => 99999,
                'order' => 0,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['authors.0.user_id']);
});

// Filtering Tests
test('user can filter posts by status', function () {
    Post::factory()->create(['status' => 'draft', 'created_by' => $this->user->id]);
    Post::factory()->create(['status' => 'published', 'created_by' => $this->user->id]);

    $response = $this->getJson('/api/posts?filter_status=draft');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('user can search posts', function () {
    Post::factory()->create([
        'title' => 'Laravel Tutorial',
        'created_by' => $this->user->id,
    ]);
    Post::factory()->create([
        'title' => 'Vue Guide',
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/posts?filter_search=Laravel');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});
