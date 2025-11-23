<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::create(['name' => 'master']);
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'user']);
});

// Authorization Tests for Master Role
test('master can view all posts in index', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $otherUser = User::factory()->create();

    // Create posts by different users
    Post::factory()->count(3)->create(['created_by' => $master->id]);
    Post::factory()->count(2)->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($master)->getJson('/api/posts');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(5); // Should see all 5 posts
});

test('master can edit any post', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($master)->putJson("/api/posts/{$post->slug}", [
        'title' => 'Updated by Master',
        'content' => '<p>Updated content</p>',
        'status' => 'published',
    ]);

    $response->assertSuccessful();
    expect($post->fresh()->title)->toBe('Updated by Master');
});

test('master can delete any post', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($master)->deleteJson("/api/posts/{$post->slug}");

    $response->assertSuccessful();
    expect($post->fresh()->trashed())->toBeTrue();
});

test('master can view all trashed posts', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $otherUser = User::factory()->create();

    // Create and trash posts by different users
    $masterPost = Post::factory()->create(['created_by' => $master->id]);
    $userPost = Post::factory()->create(['created_by' => $otherUser->id]);

    $masterPost->delete();
    $userPost->delete();

    $response = $this->actingAs($master)->getJson('/api/posts/trash');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(2); // Should see both trashed posts
});

// Authorization Tests for Admin Role
test('admin can view all posts in index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherUser = User::factory()->create();

    Post::factory()->count(3)->create(['created_by' => $admin->id]);
    Post::factory()->count(2)->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($admin)->getJson('/api/posts');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(5);
});

test('admin can edit any post', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($admin)->putJson("/api/posts/{$post->slug}", [
        'title' => 'Updated by Admin',
        'content' => '<p>Updated content</p>',
        'status' => 'published',
    ]);

    $response->assertSuccessful();
    expect($post->fresh()->title)->toBe('Updated by Admin');
});

test('admin can delete any post', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/posts/{$post->slug}");

    $response->assertSuccessful();
    expect($post->fresh()->trashed())->toBeTrue();
});

// Authorization Tests for Regular User
test('regular user can only view their own posts', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $otherUser = User::factory()->create();

    // Create posts
    Post::factory()->count(3)->create(['created_by' => $user->id]);
    Post::factory()->count(5)->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($user)->getJson('/api/posts');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(3); // Should only see their own 3 posts
});

test('regular user can only view their own trashed posts', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $otherUser = User::factory()->create();

    $userPost = Post::factory()->create(['created_by' => $user->id]);
    $otherPost = Post::factory()->create(['created_by' => $otherUser->id]);

    $userPost->delete();
    $otherPost->delete();

    $response = $this->actingAs($user)->getJson('/api/posts/trash');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(1); // Should only see their own trashed post
});

test('regular user cannot edit others posts', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($user)->putJson("/api/posts/{$post->slug}", [
        'title' => 'Trying to update',
        'content' => '<p>Updated content</p>',
    ]);

    $response->assertForbidden();
});

test('regular user can edit their own posts', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $post = Post::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/posts/{$post->slug}", [
        'title' => 'My Updated Title',
        'content' => '<p>My updated content</p>',
        'status' => 'published',
    ]);

    $response->assertSuccessful();
    expect($post->fresh()->title)->toBe('My Updated Title');
});

test('regular user cannot delete others posts', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($user)->deleteJson("/api/posts/{$post->slug}");

    $response->assertForbidden();
});

test('regular user can delete their own posts', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $post = Post::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/posts/{$post->slug}");

    $response->assertSuccessful();
    expect($post->fresh()->trashed())->toBeTrue();
});

// User without role tests (default behavior)
test('user without role can only view their own posts', function () {
    $user = User::factory()->create(); // No role assigned
    $otherUser = User::factory()->create();

    Post::factory()->count(2)->create(['created_by' => $user->id]);
    Post::factory()->count(3)->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($user)->getJson('/api/posts');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(2);
});

test('user without role cannot edit others posts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $post = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($user)->putJson("/api/posts/{$post->slug}", [
        'title' => 'Trying to update',
        'content' => '<p>Content</p>',
    ]);

    $response->assertForbidden();
});

// Bulk operations authorization
test('regular user cannot bulk delete others posts', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $otherUser = User::factory()->create();

    $userPost = Post::factory()->create(['created_by' => $user->id]);
    $otherPost = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($user)->deleteJson('/api/posts/bulk', [
        'ids' => [$userPost->id, $otherPost->id],
    ]);

    $response->assertForbidden();
});

test('admin can bulk delete any posts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherUser = User::factory()->create();

    $post1 = Post::factory()->create(['created_by' => $admin->id]);
    $post2 = Post::factory()->create(['created_by' => $otherUser->id]);

    $response = $this->actingAs($admin)->deleteJson('/api/posts/bulk', [
        'ids' => [$post1->id, $post2->id],
    ]);

    $response->assertSuccessful();
    expect($post1->fresh()->trashed())->toBeTrue();
    expect($post2->fresh()->trashed())->toBeTrue();
});
