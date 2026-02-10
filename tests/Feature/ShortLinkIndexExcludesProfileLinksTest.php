<?php

use App\Models\Project;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index endpoint excludes user profile short links', function () {
    $user = User::factory()->create();

    // Create a user - this will automatically create a profile short link
    $profileUser = User::factory()->create([
        'username' => 'johndoe',
    ]);

    // Create a regular short link
    $regularShortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'regularlink',
        'destination_url' => 'https://example.com',
    ]);

    // Authenticate and get short links
    $response = $this->actingAs($user)->getJson('/api/short-links');

    $response->assertOk();

    // Get the slugs from the response
    $slugs = collect($response->json('data'))->pluck('slug')->toArray();

    // Regular short link should be included
    expect($slugs)->toContain('regularlink');

    // User profile short link should NOT be included
    expect($slugs)->not->toContain('johndoe');
});

test('index endpoint excludes project profile short links', function () {
    $user = User::factory()->create();

    // Create a project - this will automatically create a profile short link
    $project = Project::factory()->create([
        'username' => 'megaproject',
        'created_by' => $user->id,
    ]);

    // Create a regular short link
    $regularShortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'projectlink',
        'destination_url' => 'https://example.com/project',
    ]);

    // Authenticate and get short links
    $response = $this->actingAs($user)->getJson('/api/short-links');

    $response->assertOk();

    // Get the slugs from the response
    $slugs = collect($response->json('data'))->pluck('slug')->toArray();

    // Regular short link should be included
    expect($slugs)->toContain('projectlink');

    // Project profile short link should NOT be included
    expect($slugs)->not->toContain('megaproject');
});

test('trash endpoint excludes user profile short links', function () {
    $user = User::factory()->create();

    // Create a user - this will automatically create a profile short link
    $profileUser = User::factory()->create([
        'username' => 'johndoe',
    ]);

    // Create a regular short link
    $regularShortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'deletedlink',
        'destination_url' => 'https://example.com',
    ]);

    // Delete both the user and the regular short link
    $profileUser->delete();
    $regularShortLink->delete();

    // Authenticate and get trashed short links
    $response = $this->actingAs($user)->getJson('/api/short-links/trash');

    $response->assertOk();

    // Get the slugs from the response
    $slugs = collect($response->json('data'))->pluck('slug')->toArray();

    // Regular short link should be included
    expect($slugs)->toContain('deletedlink');

    // User profile short link should NOT be included
    expect($slugs)->not->toContain('johndoe');
});

test('trash endpoint excludes project profile short links', function () {
    $user = User::factory()->create();

    // Create a project - this will automatically create a profile short link
    $project = Project::factory()->create([
        'username' => 'megaproject',
        'created_by' => $user->id,
    ]);

    // Create a regular short link
    $regularShortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'deletedprojectlink',
        'destination_url' => 'https://example.com/deleted-project',
    ]);

    // Delete both the project and the regular short link
    $project->delete();
    $regularShortLink->delete();

    // Authenticate and get trashed short links
    $response = $this->actingAs($user)->getJson('/api/short-links/trash');

    $response->assertOk();

    // Get the slugs from the response
    $slugs = collect($response->json('data'))->pluck('slug')->toArray();

    // Regular short link should be included
    expect($slugs)->toContain('deletedprojectlink');

    // Project profile short link should NOT be included
    expect($slugs)->not->toContain('megaproject');
});

test('user profile is accessible via resolve endpoint without short link', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'status' => 'active',
    ]);

    // No auto-created short link
    $shortLink = ShortLink::where('slug', 'testuser')->first();
    expect($shortLink)->toBeNull();

    // But user profile is accessible via resolve endpoint
    $response = $this->getJson('/api/resolve/testuser');

    $response->assertOk()
        ->assertJson([
            'type' => 'user',
        ]);
});
