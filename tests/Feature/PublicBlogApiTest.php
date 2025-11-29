<?php

use App\Models\ApiConsumer;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Tags\Tag;

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
    $response = $this->getJson('/api/public/blog/posts');

    $response->assertStatus(401);
});

test('public API accepts valid API key', function () {
    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/public/blog/posts');

    $response->assertSuccessful();
});

test('public API rejects invalid API key', function () {
    $response = $this->withHeaders([
        'X-API-Key' => 'invalid_key',
    ])->getJson('/api/public/blog/posts');

    $response->assertStatus(401);
});

test('public API rejects inactive API consumer', function () {
    $this->apiConsumer->update(['is_active' => false]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/public/blog/posts');

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
    ])->getJson('/api/public/blog/posts');

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
    ])->getJson('/api/public/blog/posts/test-post');

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
    ])->getJson('/api/public/blog/posts/draft-post');

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
    ])->getJson('/api/public/blog/posts/private-post');

    $response->assertStatus(404);
});

// Filtering Tests
test('can filter posts by category', function () {
    $category = Tag::findOrCreate('Technology', 'category');

    $techPost = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);
    $techPost->attachTag($category);

    $otherPost = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson("/api/public/blog/posts?category={$category->slug}");

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
    ])->getJson('/api/public/blog/posts?search=Laravel');

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
    ])->getJson('/api/public/blog/posts/featured');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

// Categories Tests
// TODO: Update category endpoints to use Spatie Tags instead of Category model
// test('can retrieve public categories', function () {
//     $category1 = Tag::findOrCreate('Technology', 'category');
//     $category2 = Tag::findOrCreate('Science', 'category');
//     $category3 = Tag::findOrCreate('Business', 'category');

//     $response = $this->withHeaders([
//         'X-API-Key' => 'pk_test_123456789',
//     ])->getJson('/api/public/blog/categories');

//     $response->assertSuccessful()
//         ->assertJsonCount(3, 'data');
// });

// test('can retrieve single category by slug', function () {
//     $category = Tag::findOrCreate('Technology', 'category');

//     $response = $this->withHeaders([
//         'X-API-Key' => 'pk_test_123456789',
//     ])->getJson('/api/public/blog/categories/technology');

//     $response->assertSuccessful()
//         ->assertJson([
//             'data' => [
//                 'name' => 'Technology',
//                 'slug' => 'technology',
//             ],
//         ]);
// });

test('can retrieve posts by category', function () {
    $category = Tag::findOrCreate('Technology', 'category');

    $post = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);
    $post->attachTag($category);

    Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/public/blog/categories/technology/posts');

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
        'order' => 0,
    ]);

    Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/public/blog/authors/johndoe/posts');

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
    ])->getJson('/api/public/blog/posts?per_page=10');

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
    ])->getJson("/api/public/blog/posts/{$post->slug}");

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
                'visits_count',
                'created_at',
            ],
        ]);
});

test('viewing post increments visit count', function () {
    $post = Post::factory()->create([
        'slug' => 'test-post',
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    expect($post->visits()->count())->toBe(0);

    $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/public/blog/posts/test-post');

    $post->refresh();
    expect($post->visits()->count())->toBe(1);
});

// API Consumer Updates Tests
test('API request updates last_used_at', function () {
    $originalLastUsed = $this->apiConsumer->last_used_at;

    sleep(1);

    $this->withHeaders([
        'X-API-Key' => 'pk_test_123456789',
    ])->getJson('/api/public/blog/posts');

    $this->apiConsumer->refresh();

    expect($this->apiConsumer->last_used_at)
        ->not->toBe($originalLastUsed);
});

// Rate Limiting Tests
test('rate limit is enforced for consumers with limit', function () {
    $consumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_rate_limit_test',
        'is_active' => true,
        'rate_limit' => 2, // Only 2 requests per minute
    ]);

    // First 2 requests should succeed
    $this->withHeaders(['X-API-Key' => 'pk_rate_limit_test'])
        ->getJson('/api/public/blog/posts')
        ->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_rate_limit_test'])
        ->getJson('/api/public/blog/posts')
        ->assertSuccessful();

    // Third request should be rate limited
    $this->withHeaders(['X-API-Key' => 'pk_rate_limit_test'])
        ->getJson('/api/public/blog/posts')
        ->assertStatus(429)
        ->assertJson([
            'message' => 'Too many requests',
        ]);
});

test('unlimited rate limit allows many requests', function () {
    $consumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_unlimited_test',
        'is_active' => true,
        'rate_limit' => 0, // Unlimited
    ]);

    // Make many requests - all should succeed
    for ($i = 0; $i < 10; $i++) {
        $this->withHeaders(['X-API-Key' => 'pk_unlimited_test'])
            ->getJson('/api/public/blog/posts')
            ->assertSuccessful();
    }
});
