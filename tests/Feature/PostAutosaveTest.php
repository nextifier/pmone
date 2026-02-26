<?php

use App\Models\Post;
use App\Models\PostAutosave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Autosave for New Post Tests
test('user can autosave a new post', function () {
    $autosaveData = [
        'title' => 'Draft Post Title',
        'excerpt' => 'Draft excerpt',
        'content' => '<p>Draft content</p>',
        'content_format' => 'html',
        'status' => 'draft',
        'visibility' => 'public',
        'featured' => false,
    ];

    $response = $this->postJson('/api/posts/autosave', $autosaveData);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'post_id',
                'user_id',
                'title',
                'content',
                'is_for_new_post',
            ],
        ]);

    $this->assertDatabaseHas('post_autosaves', [
        'user_id' => $this->user->id,
        'post_id' => null, // New post
        'title' => 'Draft Post Title',
    ]);
});

test('autosave for new post can be retrieved', function () {
    // Create autosave
    $autosave = PostAutosave::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => null, // New post
        'title' => 'Autosaved Title',
        'content' => '<p>Autosaved content</p>',
    ]);

    // Retrieve autosave
    $response = $this->getJson('/api/posts/autosave');

    $response->assertSuccessful()
        ->assertJson([
            'data' => [
                'id' => $autosave->id,
                'title' => 'Autosaved Title',
                'is_for_new_post' => true,
            ],
        ]);
});

test('autosave allows incomplete data', function () {
    $autosaveData = [
        'title' => '', // Empty title is OK for autosave
        'content' => '<p>Some content...</p>',
    ];

    $response = $this->postJson('/api/posts/autosave', $autosaveData);

    $response->assertSuccessful();

    $this->assertDatabaseHas('post_autosaves', [
        'user_id' => $this->user->id,
        'content' => '<p>Some content...</p>',
    ]);
});

// Autosave for Existing Post Tests
test('user can autosave an existing post', function () {
    $post = Post::factory()->create([
        'title' => 'Published Post',
        'content' => '<p>Published content</p>',
        'status' => 'published',
        'created_by' => $this->user->id,
    ]);

    $autosaveData = [
        'post_id' => $post->id,
        'title' => 'Updated Title (Draft)',
        'content' => '<p>Updated content (not published yet)</p>',
        'status' => 'published',
    ];

    $response = $this->postJson('/api/posts/autosave', $autosaveData);

    $response->assertSuccessful();

    // Published post should remain unchanged
    $post->refresh();
    expect($post->title)->toBe('Published Post');
    expect($post->content)->toBe('<p>Published content</p>');

    // Autosave should contain the new changes
    $this->assertDatabaseHas('post_autosaves', [
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'title' => 'Updated Title (Draft)',
        'content' => '<p>Updated content (not published yet)</p>',
    ]);
});

test('autosave for existing post can be retrieved', function () {
    $post = Post::factory()->create([
        'created_by' => $this->user->id,
    ]);

    $autosave = PostAutosave::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'title' => 'Autosaved Changes',
    ]);

    $response = $this->getJson('/api/posts/autosave?post_id='.$post->id);

    $response->assertSuccessful()
        ->assertJson([
            'data' => [
                'id' => $autosave->id,
                'post_id' => $post->id,
                'title' => 'Autosaved Changes',
                'is_for_existing_post' => true,
            ],
        ]);
});

test('autosave upserts correctly - only one autosave per user per post', function () {
    $post = Post::factory()->create([
        'created_by' => $this->user->id,
    ]);

    // First autosave
    $this->postJson('/api/posts/autosave', [
        'post_id' => $post->id,
        'title' => 'First Version',
        'content' => '<p>First content</p>',
    ]);

    // Second autosave (should update, not create new)
    $this->postJson('/api/posts/autosave', [
        'post_id' => $post->id,
        'title' => 'Second Version',
        'content' => '<p>Second content</p>',
    ]);

    // Should only have one autosave record
    $autosaves = PostAutosave::where('user_id', $this->user->id)
        ->where('post_id', $post->id)
        ->get();

    expect($autosaves)->toHaveCount(1);
    expect($autosaves->first()->title)->toBe('Second Version');
});

test('different users can have separate autosaves for same post', function () {
    $post = Post::factory()->create(['created_by' => $this->user->id]);
    $user2 = User::factory()->create();
    // Make user2 a co-author so they have update permission
    $post->authors()->attach($user2->id, ['order' => 1]);

    // User 1 autosave
    $this->actingAs($this->user)
        ->postJson('/api/posts/autosave', [
            'post_id' => $post->id,
            'title' => 'User 1 Version',
            'content' => '<p>User 1 content</p>',
        ])->assertSuccessful();

    // User 2 autosave
    $this->actingAs($user2)
        ->postJson('/api/posts/autosave', [
            'post_id' => $post->id,
            'title' => 'User 2 Version',
            'content' => '<p>User 2 content</p>',
        ])->assertSuccessful();

    // Should have two separate autosaves
    expect(PostAutosave::where('post_id', $post->id)->count())->toBe(2);

    $this->assertDatabaseHas('post_autosaves', [
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'title' => 'User 1 Version',
    ]);

    $this->assertDatabaseHas('post_autosaves', [
        'user_id' => $user2->id,
        'post_id' => $post->id,
        'title' => 'User 2 Version',
    ]);
});

// Discard Tests
test('user can discard autosave for new post', function () {
    PostAutosave::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => null,
    ]);

    $response = $this->deleteJson('/api/posts/autosave');

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Autosave discarded successfully',
        ]);

    $this->assertDatabaseMissing('post_autosaves', [
        'user_id' => $this->user->id,
        'post_id' => null,
    ]);
});

test('user can discard autosave for existing post', function () {
    $post = Post::factory()->create([
        'created_by' => $this->user->id,
    ]);

    PostAutosave::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => $post->id,
    ]);

    $response = $this->deleteJson('/api/posts/autosave?post_id='.$post->id);

    $response->assertSuccessful();

    $this->assertDatabaseMissing('post_autosaves', [
        'user_id' => $this->user->id,
        'post_id' => $post->id,
    ]);
});

test('discarding non-existent autosave returns success', function () {
    $response = $this->deleteJson('/api/posts/autosave');

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'No autosave to discard',
        ]);
});

// Preview Tests
test('user can preview changes between published and autosave', function () {
    $post = Post::factory()->create([
        'title' => 'Original Title',
        'content' => '<p>Original content</p>',
        'status' => 'published',
        'created_by' => $this->user->id,
    ]);

    PostAutosave::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => $post->id,
        'title' => 'Modified Title',
        'content' => '<p>Modified content</p>',
    ]);

    $response = $this->getJson("/api/posts/{$post->slug}/preview");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'published' => ['title', 'content'],
                'autosave' => ['title', 'content'],
                'has_changes',
            ],
        ])
        ->assertJson([
            'data' => [
                'published' => [
                    'title' => 'Original Title',
                    'content' => '<p>Original content</p>',
                ],
                'autosave' => [
                    'title' => 'Modified Title',
                    'content' => '<p>Modified content</p>',
                ],
                'has_changes' => true,
            ],
        ]);
});

test('preview returns 404 when no autosave exists', function () {
    $post = Post::factory()->create([
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson("/api/posts/{$post->slug}/preview");

    $response->assertNotFound()
        ->assertJson([
            'message' => 'No autosave found for preview',
        ]);
});

// Authorization Tests
test('user cannot autosave post they do not have permission to edit', function () {
    $otherUser = User::factory()->create();
    $post = Post::factory()->create([
        'created_by' => $otherUser->id,
        'status' => 'published',
    ]);

    $response = $this->postJson('/api/posts/autosave', [
        'post_id' => $post->id,
        'title' => 'Unauthorized Edit',
        'content' => '<p>Unauthorized content</p>',
    ]);

    $response->assertForbidden();
});

test('unauthenticated user cannot use autosave', function () {
    auth()->logout();

    $response = $this->postJson('/api/posts/autosave', [
        'title' => 'Test',
        'content' => '<p>Test</p>',
    ]);

    $response->assertUnauthorized();
});

// Autosave with Media and Relationships
test('autosave can store media and relationships as JSON', function () {
    $autosaveData = [
        'title' => 'Post with Media',
        'content' => '<p>Content</p>',
        'tmp_media' => [
            'featured_image' => 'tmp-123',
            'og_image' => 'tmp-456',
            'featured_image_caption' => 'Test caption',
        ],
        'tags' => ['Laravel', 'PHP', 'Testing'],
        'authors' => [
            ['user_id' => $this->user->id, 'order' => 0],
        ],
    ];

    $response = $this->postJson('/api/posts/autosave', $autosaveData);

    $response->assertSuccessful();

    $this->assertDatabaseHas('post_autosaves', [
        'user_id' => $this->user->id,
        'title' => 'Post with Media',
    ]);

    $autosave = PostAutosave::where('user_id', $this->user->id)->first();
    expect($autosave->tmp_media)->toEqual([
        'featured_image' => 'tmp-123',
        'og_image' => 'tmp-456',
        'featured_image_caption' => 'Test caption',
    ]);
    expect($autosave->tags)->toEqual(['Laravel', 'PHP', 'Testing']);
    expect($autosave->authors)->toHaveCount(1);
});
