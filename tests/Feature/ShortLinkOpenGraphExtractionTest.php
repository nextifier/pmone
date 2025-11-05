<?php

use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('automatically extracts og metadata when short link is created', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <meta property="og:title" content="Amazing Article" />
        <meta property="og:description" content="Read this amazing article" />
        <meta property="og:image" content="https://example.com/og-image.jpg" />
        <meta property="og:type" content="article" />
    </head>
    </html>
    HTML;

    Http::fake([
        'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $user = User::factory()->create();

    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'auto-extract',
        'destination_url' => 'https://example.com/article',
        'is_active' => true,
    ]);

    // Refresh to get the updated values from observer
    $shortLink->refresh();

    expect($shortLink->og_title)->toBe('Amazing Article');
    expect($shortLink->og_description)->toBe('Read this amazing article');
    expect($shortLink->og_image)->toBe('https://example.com/og-image.jpg');
    expect($shortLink->og_type)->toBe('article');
});

test('re-extracts og metadata when destination url is updated', function () {
    $htmlOld = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <meta property="og:title" content="Old Title" />
    </head>
    </html>
    HTML;

    $htmlNew = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <meta property="og:title" content="New Title" />
    </head>
    </html>
    HTML;

    Http::fake([
        'example.com/old' => Http::response($htmlOld, 200, ['Content-Type' => 'text/html']),
        'example.com/new' => Http::response($htmlNew, 200, ['Content-Type' => 'text/html']),
    ]);

    $user = User::factory()->create();

    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'update-test',
        'destination_url' => 'https://example.com/old',
        'is_active' => true,
    ]);

    $shortLink->refresh();
    expect($shortLink->og_title)->toBe('Old Title');

    // Update destination URL
    $shortLink->update([
        'destination_url' => 'https://example.com/new',
    ]);

    $shortLink->refresh();
    expect($shortLink->og_title)->toBe('New Title');
});

test('does not re-extract og metadata when other fields are updated', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <meta property="og:title" content="Original Title" />
    </head>
    </html>
    HTML;

    Http::fake([
        'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $user = User::factory()->create();

    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'no-extract',
        'destination_url' => 'https://example.com/article',
        'is_active' => true,
    ]);

    Http::assertSentCount(1); // Only one request during creation

    // Update is_active (not destination_url)
    $shortLink->update([
        'is_active' => false,
    ]);

    Http::assertSentCount(1); // Still only one request, no re-extraction
});

test('handles og extraction failure gracefully', function () {
    Http::fake([
        'example.com/*' => Http::response(null, 404),
    ]);

    $user = User::factory()->create();

    // Should not throw exception
    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'fail-gracefully',
        'destination_url' => 'https://example.com/not-found',
        'is_active' => true,
    ]);

    $shortLink->refresh();

    // Short link should still be created
    expect($shortLink->id)->not->toBeNull();
    expect($shortLink->og_title)->toBeNull();
    expect($shortLink->og_description)->toBeNull();
    expect($shortLink->og_image)->toBeNull();
});

test('extracts og metadata from url without og tags using fallback', function () {
    $html = <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <title>Page Title</title>
        <meta name="description" content="Page description" />
    </head>
    </html>
    HTML;

    Http::fake([
        'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
    ]);

    $user = User::factory()->create();

    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'fallback',
        'destination_url' => 'https://example.com/page',
        'is_active' => true,
    ]);

    $shortLink->refresh();

    expect($shortLink->og_title)->toBe('Page Title'); // Fallback to <title>
    expect($shortLink->og_description)->toBe('Page description'); // Fallback to meta description
});
