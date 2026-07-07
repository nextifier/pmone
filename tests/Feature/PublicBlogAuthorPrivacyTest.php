<?php

use App\Models\ApiConsumer;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_blog_key', 'is_active' => true]);

    $this->author = User::factory()->create(['email_verified_at' => now()]);
    $this->post = Post::factory()->published()->create(['created_by' => $this->author->id]);
    $this->post->authors()->attach($this->author->id, ['order' => 0]);
});

test('public blog payloads do not expose author emails', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_blog_key'])
        ->getJson('/api/public/blog/posts')
        ->assertOk();

    $authors = $response->json('data.0.authors');

    expect($authors)->not->toBeEmpty();
    expect($authors[0])->toHaveKey('name')->not->toHaveKey('email');
});

test('authenticated admin payloads still expose author emails', function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('master');

    $response = $this->actingAs($admin)
        ->getJson('/api/posts')
        ->assertOk();

    $authors = $response->json('data.0.authors');

    expect($authors)->not->toBeEmpty();
    expect($authors[0])->toHaveKey('email');
});
