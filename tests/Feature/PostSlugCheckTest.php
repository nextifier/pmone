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

    $this->user = User::factory()->create();
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

test('check-slug returns available true for unused slug', function () {
    $response = $this->getJson('/api/posts/check-slug?slug=unique-test-slug');

    $response->assertSuccessful()
        ->assertJson([
            'available' => true,
            'slug' => 'unique-test-slug',
        ]);
});

test('check-slug returns available false for existing slug', function () {
    Post::factory()->create([
        'slug' => 'existing-slug',
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/posts/check-slug?slug=existing-slug');

    $response->assertSuccessful()
        ->assertJson([
            'available' => false,
            'slug' => 'existing-slug',
        ]);
});

test('check-slug with exclude_id returns available for own post slug', function () {
    $post = Post::factory()->create([
        'slug' => 'my-post-slug',
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson("/api/posts/check-slug?slug=my-post-slug&exclude_id={$post->id}");

    $response->assertSuccessful()
        ->assertJson([
            'available' => true,
            'slug' => 'my-post-slug',
        ]);
});

test('check-slug with exclude_id returns unavailable for other post slug', function () {
    $otherPost = Post::factory()->create([
        'slug' => 'other-post-slug',
        'created_by' => $this->user->id,
    ]);

    $myPost = Post::factory()->create([
        'slug' => 'my-post-slug',
        'created_by' => $this->user->id,
    ]);

    // Try to use other post's slug while excluding my post
    $response = $this->getJson("/api/posts/check-slug?slug=other-post-slug&exclude_id={$myPost->id}");

    $response->assertSuccessful()
        ->assertJson([
            'available' => false,
            'slug' => 'other-post-slug',
        ]);
});

test('check-slug requires slug parameter', function () {
    $response = $this->getJson('/api/posts/check-slug');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('check-slug validates slug max length', function () {
    $longSlug = str_repeat('a', 300);

    $response = $this->getJson("/api/posts/check-slug?slug={$longSlug}");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});

test('check-slug requires authentication', function () {
    auth()->logout();

    $response = $this->getJson('/api/posts/check-slug?slug=test-slug');

    $response->assertStatus(401);
});
