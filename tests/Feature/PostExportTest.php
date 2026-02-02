<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

    $this->masterUser = User::factory()->create();
    $this->masterUser->assignRole('master');
});

it('can export posts to csv', function () {
    Post::factory()->count(3)->create([
        'status' => 'published',
        'created_by' => $this->masterUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get('/api/posts/export');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=utf-8');
    $response->assertHeader('content-disposition');
});

it('can export posts with status filter', function () {
    Post::factory()->count(2)->create([
        'status' => 'published',
        'created_by' => $this->masterUser->id,
    ]);

    Post::factory()->count(3)->create([
        'status' => 'draft',
        'created_by' => $this->masterUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get('/api/posts/export?filter_status=published');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=utf-8');
});

it('can export posts with creator filter', function () {
    $anotherUser = User::factory()->create();
    $anotherUser->assignRole('writer');

    Post::factory()->count(2)->create([
        'created_by' => $this->masterUser->id,
    ]);

    Post::factory()->count(3)->create([
        'created_by' => $anotherUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get('/api/posts/export?filter_creator='.$this->masterUser->id);

    $response->assertSuccessful();
});

it('returns 404 when no posts found for images export', function () {
    // Export with non-existent status should return 404
    $response = $this->actingAs($this->masterUser)->get('/api/posts/export/with-images?filter_status=nonexistent_status');

    $response->assertNotFound();
});

it('requires authentication for export', function () {
    $response = $this->get('/api/posts/export');

    $response->assertUnauthorized();
});

it('requires authentication for images export', function () {
    $response = $this->get('/api/posts/export/with-images');

    $response->assertUnauthorized();
});

it('respects sorting in export', function () {
    Post::factory()->count(3)->create([
        'created_by' => $this->masterUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get('/api/posts/export?sort=-created_at');

    $response->assertSuccessful();
});

it('can export with visibility filter', function () {
    Post::factory()->count(2)->create([
        'visibility' => 'public',
        'created_by' => $this->masterUser->id,
    ]);

    Post::factory()->count(1)->create([
        'visibility' => 'private',
        'created_by' => $this->masterUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get('/api/posts/export?filter_visibility=public');

    $response->assertSuccessful();
});

it('can export with featured filter', function () {
    Post::factory()->count(2)->create([
        'featured' => true,
        'created_by' => $this->masterUser->id,
    ]);

    Post::factory()->count(3)->create([
        'featured' => false,
        'created_by' => $this->masterUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get('/api/posts/export?filter_featured=true');

    $response->assertSuccessful();
});

it('can export with source filter', function () {
    Post::factory()->count(2)->create([
        'source' => 'native',
        'created_by' => $this->masterUser->id,
    ]);

    Post::factory()->count(1)->create([
        'source' => 'ghost',
        'created_by' => $this->masterUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get('/api/posts/export?filter_source=native');

    $response->assertSuccessful();
});

it('can export with multiple filters combined', function () {
    Post::factory()->count(2)->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->masterUser->id,
    ]);

    Post::factory()->count(1)->create([
        'status' => 'draft',
        'visibility' => 'private',
        'created_by' => $this->masterUser->id,
    ]);

    $response = $this->actingAs($this->masterUser)->get(
        '/api/posts/export?filter_status=published&filter_visibility=public'
    );

    $response->assertSuccessful();
});
