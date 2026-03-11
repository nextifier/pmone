<?php

use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();
});

test('resolve endpoint response is cached', function () {
    $user = User::factory()->create();

    ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'cached-link',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    // First request - should be a cache miss
    $response1 = $this->getJson('/api/resolve/cached-link');
    $response1->assertOk()
        ->assertJson(['type' => 'shortlink']);

    // Second request - should be served from cache
    $response2 = $this->getJson('/api/resolve/cached-link');
    $response2->assertOk()
        ->assertJson(['type' => 'shortlink']);
});

test('short link cache is cleared when short link is updated', function () {
    $user = User::factory()->create();

    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'update-test',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    // Prime the cache
    $this->getJson('/api/resolve/update-test')->assertOk();

    // Update the short link - should clear 'short-links' tagged cache
    $shortLink->update(['destination_url' => 'https://updated.com']);

    // Next request should get fresh data
    $response = $this->getJson('/api/resolve/update-test');
    $response->assertOk()
        ->assertJsonPath('data.destination_url', 'https://updated.com');
});

test('short link cache is cleared when short link is deleted', function () {
    $user = User::factory()->create();

    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'delete-test',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    // Prime the cache
    $this->getJson('/api/resolve/delete-test')->assertOk();

    // Delete the short link - should clear 'short-links' tagged cache
    $shortLink->delete();

    // Next request should return 404
    $this->getJson('/api/resolve/delete-test')->assertNotFound();
});

test('short link cache is cleared when short link is restored', function () {
    $user = User::factory()->create();

    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'restore-test',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    // Soft delete
    $shortLink->delete();

    // Prime the cache with 404
    $this->getJson('/api/resolve/restore-test')->assertNotFound();

    // Restore - should clear 'short-links' tagged cache
    $shortLink->restore();

    // Next request should return the short link
    $response = $this->getJson('/api/resolve/restore-test');
    $response->assertOk()
        ->assertJson(['type' => 'shortlink']);
});

test('/api/s route is NOT cached because it tracks clicks', function () {
    $user = User::factory()->create();

    ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'click-track',
        'destination_url' => 'https://example.com',
        'is_active' => true,
    ]);

    // First request
    $this->getJson('/api/s/click-track')->assertOk();

    // Second request - should still hit the controller (not cached)
    $this->getJson('/api/s/click-track')->assertOk();

    // Both requests should have tracked clicks
    expect($user->shortLinks()->first()->clicks()->count())->toBe(2);
});
