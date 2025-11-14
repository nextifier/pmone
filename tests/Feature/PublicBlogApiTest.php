<?php

use App\Models\ApiConsumer;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiConsumer = ApiConsumer::factory()->create([
        'name' => 'Test Website',
        'api_key' => 'pk_test_123456789',
        'is_active' => true,
        'rate_limit' => 60,
        'allowed_origins' => ['https://example.com'],
    ]);

    $this->author = User::factory()->create();
});

// API Key Authentication Tests
test('public API requires API key', function () {
    $response = $this->getJson('/api/blog/posts');

    $response->assertStatus(401);
});

test('public API accepts valid API key', function () {
    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts');

    $response->assertSuccessful();
});

test('public API rejects invalid API key', function () {
    $response = $this->withHeaders([
        'X-API-Key' => 'invalid_key',
    ])->getJson('/api/blog/posts');

    $response->assertStatus(401);
});

test('public API rejects inactive API consumer', function () {
    $this->apiConsumer->update(['is_active' => false]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts');

    $response->assertStatus(401);
});

// Get Posts Tests
test('can retrieve published posts', function () {
    Post::factory()->count(3)->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    Post::factory()->create([
        'status' => 'draft',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can retrieve single published post by slug', function () {
    $post = Post::factory()->create([
        'title' => 'Test Post',
        'slug' => 'test-post',
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts/test-post');

    $response->assertSuccessful()
        ->assertJson([
            'data' => [
                'title' => 'Test Post',
                'slug' => 'test-post',
            ],
        ]);
});

test('cannot retrieve draft posts', function () {
    Post::factory()->create([
        'slug' => 'draft-post',
        'status' => 'draft',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts/draft-post');

    $response->assertStatus(404);
});

test('cannot retrieve private posts', function () {
    Post::factory()->create([
        'slug' => 'private-post',
        'status' => 'published',
        'visibility' => 'private',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts/private-post');

    $response->assertStatus(404);
});

// Filtering Tests
test('can filter posts by category', function () {
    $category = Category::factory()->create(['name' => 'Technology']);

    $techPost = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);
    $techPost->categories()->attach($category);

    Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson("/api/blog/posts?category={$category->slug}");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('can search posts', function () {
    Post::factory()->create([
        'title' => 'Laravel Framework Guide',
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    Post::factory()->create([
        'title' => 'Vue.js Tutorial',
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts?search=Laravel');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('can retrieve featured posts', function () {
    Post::factory()->count(2)->create([
        'featured' => true,
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    Post::factory()->create([
        'featured' => false,
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts/featured');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

// Categories Tests
test('can retrieve public categories', function () {
    Category::factory()->count(3)->create(['visibility' => 'public']);
    Category::factory()->create(['visibility' => 'private']);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/categories');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can retrieve single category by slug', function () {
    $category = Category::factory()->create([
        'name' => 'Technology',
        'slug' => 'technology',
        'visibility' => 'public',
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/categories/technology');

    $response->assertSuccessful()
        ->assertJson([
            'data' => [
                'name' => 'Technology',
                'slug' => 'technology',
            ],
        ]);
});

test('can retrieve posts by category', function () {
    $category = Category::factory()->create([
        'slug' => 'technology',
        'visibility' => 'public',
    ]);

    $post = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);
    $post->categories()->attach($category);

    Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/categories/technology/posts');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

// Posts by Author Tests
test('can retrieve posts by author', function () {
    $author = User::factory()->create(['username' => 'johndoe']);

    $post = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $author->id,
    ]);

    $post->authors()->attach($author, [
        'role' => 'primary_author',
        'order' => 0,
    ]);

    Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/authors/johndoe/posts');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

// Pagination Tests
test('posts are paginated', function () {
    Post::factory()->count(25)->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts?per_page=10');

    $response->assertSuccessful()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
});

// Post Response Structure Tests
test('post response includes required fields', function () {
    $post = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson("/api/blog/posts/{$post->slug}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'slug',
                'excerpt',
                'content',
                'status',
                'visibility',
                'published_at',
                'reading_time',
                'view_count',
                'created_at',
            ],
        ]);
});

test('viewing post increments view count', function () {
    $post = Post::factory()->create([
        'slug' => 'test-post',
        'status' => 'published',
        'visibility' => 'public',
        'view_count' => 0,
        'created_by' => $this->author->id,
    ]);

    $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts/test-post');

    $post->refresh();
    expect($post->view_count)->toBe(1);
});

// API Consumer Updates Tests
test('API request updates last_used_at', function () {
    $originalLastUsed = $this->apiConsumer->last_used_at;

    sleep(1);

    $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/blog/posts');

    $this->apiConsumer->refresh();

    expect($this->apiConsumer->last_used_at)
        ->not->toBe($originalLastUsed);
});
