<?php

use App\Models\Project;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('does not auto-create short link when user is created', function () {
    $user = User::factory()->create([
        'username' => 'johndoe',
    ]);

    $shortLink = ShortLink::where('user_id', $user->id)
        ->where('slug', 'johndoe')
        ->first();

    expect($shortLink)->toBeNull();
});

test('creates short link when project is created', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'name' => 'Mega Project',
        'username' => 'megaproject',
        'created_by' => $user->id,
    ]);

    $shortLink = ShortLink::where('slug', 'megaproject')
        ->first();

    expect($shortLink)->not->toBeNull();
    expect($shortLink->destination_url)->toContain('/projects/megaproject');
    expect($shortLink->is_active)->toBeTrue();
});

test('updates short link when project username is changed', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'name' => 'Old Project Name',
        'username' => 'oldprojectname',
        'created_by' => $user->id,
    ]);

    $oldShortLink = ShortLink::where('slug', 'oldprojectname')
        ->first();

    expect($oldShortLink)->not->toBeNull();

    // Update project username
    $project->update(['username' => 'newprojectname']);

    // Short link should be updated
    $updatedShortLink = ShortLink::where('slug', 'newprojectname')
        ->first();

    expect($updatedShortLink)->not->toBeNull();
    expect($updatedShortLink->id)->toBe($oldShortLink->id);
    expect($updatedShortLink->destination_url)->toContain('/projects/newprojectname');

    // Old slug should not exist
    $oldSlugExists = ShortLink::where('slug', 'oldprojectname')->exists();
    expect($oldSlugExists)->toBeFalse();
});

test('soft deletes short link when project is soft deleted', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'name' => 'Test Project',
        'username' => 'testproject',
        'created_by' => $user->id,
    ]);

    $shortLink = ShortLink::where('slug', 'testproject')
        ->first();

    expect($shortLink)->not->toBeNull();

    // Soft delete project
    $project->delete();

    // Short link should be soft deleted
    $deletedShortLink = ShortLink::withTrashed()
        ->where('slug', 'testproject')
        ->first();

    expect($deletedShortLink)->not->toBeNull();
    expect($deletedShortLink->trashed())->toBeTrue();
});

test('restores short link when project is restored', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'name' => 'Test Project',
        'username' => 'testproject',
        'created_by' => $user->id,
    ]);

    // Soft delete project
    $project->delete();

    // Restore project
    $project->restore();

    // Short link should be restored
    $shortLink = ShortLink::where('slug', 'testproject')
        ->first();

    expect($shortLink)->not->toBeNull();
    expect($shortLink->trashed())->toBeFalse();
});

test('can access user profile via new route', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'status' => 'active',
    ]);

    $response = $this->getJson("/api/users/{$user->username}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'username',
                'name',
            ],
        ]);
});

test('can access project profile via new route', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'name' => 'Test Project',
        'username' => 'testproject',
        'status' => 'active',
        'visibility' => 'public',
        'created_by' => $user->id,
    ]);

    $response = $this->getJson("/api/projects/{$project->username}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'username',
                'name',
            ],
        ]);
});

test('can resolve short link via /api/s route', function () {
    $user = User::factory()->create();

    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'my-link',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/s/{$shortLink->slug}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'destination_url' => 'https://example.com',
            ],
        ]);
});

test('resolve endpoint returns user for username slug', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/resolve/testuser');

    $response->assertOk()
        ->assertJson([
            'type' => 'user',
        ])
        ->assertJsonStructure([
            'type',
            'data' => [
                'id',
                'username',
                'name',
            ],
        ]);
});

test('resolve endpoint returns shortlink for short link slug', function () {
    $user = User::factory()->create();

    ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'my-shortlink',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/resolve/my-shortlink');

    $response->assertOk()
        ->assertJson([
            'type' => 'shortlink',
        ])
        ->assertJsonPath('data.slug', 'my-shortlink')
        ->assertJsonPath('data.destination_url', 'https://example.com');
});

test('resolve endpoint prioritizes user over shortlink with same slug', function () {
    $user = User::factory()->create([
        'username' => 'sameslug',
        'status' => 'active',
    ]);

    ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'sameslug',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/resolve/sameslug');

    $response->assertOk()
        ->assertJson([
            'type' => 'user',
        ]);
});

test('resolve endpoint returns 404 for nonexistent slug', function () {
    $response = $this->getJson('/api/resolve/nonexistent');

    $response->assertNotFound();
});
