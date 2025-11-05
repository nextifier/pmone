<?php

use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('redirects regular users directly to destination url', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'test123',
        'destination_url' => 'https://example.com/destination',
        'is_active' => true,
    ]);

    $response = $this->get('/test123', [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);

    $response->assertRedirect('https://example.com/destination');
    $response->assertStatus(302);
});

test('serves html with og tags to crawlers', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'test123',
        'destination_url' => 'https://example.com/destination',
        'is_active' => true,
        'og_title' => 'Test Article',
        'og_description' => 'This is a test article description',
        'og_image' => 'https://example.com/image.jpg',
        'og_type' => 'article',
    ]);

    $response = $this->get('/test123', [
        'User-Agent' => 'facebookexternalhit/1.1',
    ]);

    $response->assertOk();
    $response->assertViewIs('short-link.redirect');
    $response->assertSee('og:title');
    $response->assertSee('Test Article');
    $response->assertSee('og:description');
    $response->assertSee('This is a test article description');
    $response->assertSee('og:image');
    $response->assertSee('https://example.com/image.jpg');
});

test('serves html with fallback title when og_title is null', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'test123',
        'destination_url' => 'https://example.com/destination',
        'is_active' => true,
        'og_title' => null,
    ]);

    $response = $this->get('/test123', [
        'User-Agent' => 'WhatsApp/2.0',
    ]);

    $response->assertOk();
    $response->assertSee('og:title');
    $response->assertSee('test123'); // Falls back to slug
});

test('returns 404 for inactive short links', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'inactive',
        'destination_url' => 'https://example.com/destination',
        'is_active' => false,
    ]);

    $response = $this->get('/inactive');

    $response->assertNotFound();
});

test('returns 404 for non-existent short links', function () {
    $response = $this->get('/nonexistent');

    $response->assertNotFound();
});

test('tracks clicks when accessing short link', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'trackme',
        'destination_url' => 'https://example.com/destination',
        'is_active' => true,
    ]);

    expect($shortLink->clicks()->count())->toBe(0);

    $this->get('/trackme', [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)',
    ]);

    expect($shortLink->fresh()->clicks()->count())->toBe(1);
});

test('serves og tags to different social media crawlers', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'social',
        'destination_url' => 'https://example.com/destination',
        'is_active' => true,
        'og_title' => 'Social Test',
    ]);

    $crawlers = [
        'facebookexternalhit/1.1',
        'Twitterbot/1.0',
        'WhatsApp/2.0',
        'LinkedInBot/1.0',
        'Slackbot-LinkExpanding 1.0',
        'Mozilla/5.0 (compatible; Discordbot/2.0;)',
    ];

    foreach ($crawlers as $crawler) {
        $response = $this->get('/social', [
            'User-Agent' => $crawler,
        ]);

        $response->assertOk();
        $response->assertSee('og:title');
        $response->assertSee('Social Test');
    }
});

test('view includes javascript redirect for crawlers with js enabled', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'jstest',
        'destination_url' => 'https://example.com/destination',
        'is_active' => true,
    ]);

    $response = $this->get('/jstest', [
        'User-Agent' => 'facebookexternalhit/1.1',
    ]);

    $response->assertOk();
    $response->assertSee('window.location.href', false);
    $response->assertSee('https://example.com/destination', false);
});

test('view includes meta refresh fallback', function () {
    $user = User::factory()->create();
    $shortLink = ShortLink::factory()->create([
        'user_id' => $user->id,
        'slug' => 'refresh',
        'destination_url' => 'https://example.com/destination',
        'is_active' => true,
    ]);

    $response = $this->get('/refresh', [
        'User-Agent' => 'facebookexternalhit/1.1',
    ]);

    $response->assertOk();
    $response->assertSee('<meta http-equiv="refresh"', false);
});
