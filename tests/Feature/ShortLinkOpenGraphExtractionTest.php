<?php

use App\Jobs\ExtractOpenGraphMetadata;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('dispatches job to extract og metadata when short link is created', function () {
    Queue::fake();

    $user = User::factory()->create();

    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'auto-extract',
        'destination_url' => 'https://example.com/article',
        'is_active' => true,
    ]);

    // Assert job was dispatched
    Queue::assertPushed(ExtractOpenGraphMetadata::class, function ($job) use ($shortLink) {
        return $job->shortLinkId === $shortLink->id;
    });
});

test('extracts og metadata when job is executed', function () {
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

    // Manually execute the job
    ExtractOpenGraphMetadata::dispatch($shortLink->id);

    // Refresh to get the updated values
    $shortLink->refresh();

    expect($shortLink->og_title)->toBe('Amazing Article');
    expect($shortLink->og_description)->toBe('Read this amazing article');
    expect($shortLink->og_image)->toBe('https://example.com/og-image.jpg');
    expect($shortLink->og_type)->toBe('article');
});

test('dispatches job to re-extract og metadata when destination url is updated', function () {
    Queue::fake();

    $user = User::factory()->create();

    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'update-test',
        'destination_url' => 'https://example.com/old',
        'is_active' => true,
    ]);

    // Clear previous assertions
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 1);

    // Update destination URL
    $shortLink->update([
        'destination_url' => 'https://example.com/new',
    ]);

    // Should dispatch job again
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 2);
});

test('does not dispatch job when other fields are updated', function () {
    Queue::fake();

    $user = User::factory()->create();

    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'no-extract',
        'destination_url' => 'https://example.com/article',
        'is_active' => true,
    ]);

    // Job dispatched once during creation
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 1);

    // Update is_active (not destination_url)
    $shortLink->update([
        'is_active' => false,
    ]);

    // Still only one job dispatch, no re-extraction
    Queue::assertPushed(ExtractOpenGraphMetadata::class, 1);
});

test('short link is created even when og extraction fails', function () {
    Http::fake([
        'example.com/*' => Http::response(null, 404),
    ]);

    $user = User::factory()->create();

    // Should not throw exception during creation
    $shortLink = ShortLink::create([
        'user_id' => $user->id,
        'slug' => 'fail-gracefully',
        'destination_url' => 'https://example.com/not-found',
        'is_active' => true,
    ]);

    // Short link should be created successfully
    expect($shortLink->id)->not->toBeNull();

    // OG fields will be null initially (job will process later)
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

    // Manually execute the job
    ExtractOpenGraphMetadata::dispatch($shortLink->id);

    $shortLink->refresh();

    expect($shortLink->og_title)->toBe('Page Title'); // Fallback to <title>
    expect($shortLink->og_description)->toBe('Page description'); // Fallback to meta description
});
