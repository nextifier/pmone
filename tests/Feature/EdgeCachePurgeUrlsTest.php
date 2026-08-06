<?php

use App\Support\EdgeCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Locks the shape of the URLs a tag purge sends to Cloudflare.
 *
 * Until 6 Aug 2026 these URLs carried synthetic `?__cm` / `?__lc` / `?__al`
 * params that hand-mirrored a cache-key scheme living in another repo, with no
 * test on either side. That scheme is gone; these tests exist so the next person
 * to touch the purge map finds out immediately if the URLs stop matching what
 * the event websites actually serve.
 */
beforeEach(function () {
    config([
        'edge-sites.token' => 'token-abc',
        'edge-sites.sites' => [
            ['project' => 'ioe', 'url' => 'https://indooutingexpo.co.id', 'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ],
    ]);

    Cache::forget('edge-cache:zones');
    Cache::put('edge-cache:zones', ['indooutingexpo.co.id' => 'zone-ioe'], 3600);
});

/** Capture the `files` payloads Cloudflare would have received. */
function capturedPurgeUrls(callable $action): array
{
    $sent = [];

    Http::fake(function ($request) use (&$sent) {
        $sent = array_merge($sent, $request->data()['files'] ?? []);

        return Http::response(['success' => true], 200);
    });

    $action();

    return $sent;
}

it('expands an html path across every prefixed locale but not the default', function () {
    expect(EdgeCache::localeVariants('/news', ['en', 'id', 'zh', 'ja', 'ko']))
        ->toBe(['/news', '/id/news', '/zh/news', '/ja/news', '/ko/news']);
});

it('never emits an /en prefix, because en is the unprefixed default locale', function () {
    $variants = EdgeCache::localeVariants('/tickets', ['en', 'id']);

    expect($variants)->not->toContain('/en/tickets');
});

it('keeps the root path free of a trailing slash when it expands', function () {
    expect(EdgeCache::localeVariants('/', ['en', 'id']))->toBe(['/', '/id']);
});

it('purges plain urls with no synthetic cache-key params', function () {
    $urls = capturedPurgeUrls(fn () => EdgeCache::purgeTags(['blog-posts'], [], 'ioe'));

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $url) {
        expect($url)->not->toContain('__cm')
            ->and($url)->not->toContain('__lc')
            ->and($url)->not->toContain('__al');
    }
});

it('purges the tickets page and the event payload it renders from', function () {
    $urls = capturedPurgeUrls(fn () => EdgeCache::purgeTags(['tickets'], [], 'ioe'));

    expect($urls)
        ->toContain('https://indooutingexpo.co.id/tickets')
        ->toContain('https://indooutingexpo.co.id/id/tickets')
        // The bug this closes: purging only the HTML made the re-render pull the
        // OLD poster URL back out of the still-cached event payload.
        ->toContain('https://indooutingexpo.co.id/api/event/active?locale=id');
});

it('drops the tickets page when the event itself changes', function () {
    $urls = capturedPurgeUrls(fn () => EdgeCache::purgeTags(['events'], [], 'ioe'));

    expect($urls)
        ->toContain('https://indooutingexpo.co.id/tickets')
        ->toContain('https://indooutingexpo.co.id/id/tickets');
});

it('stays off the network when no purge token is configured', function () {
    config(['edge-sites.token' => null]);
    Http::preventStrayRequests();

    EdgeCache::purgeTags(['tickets'], [], 'ioe');
})->throwsNoExceptions();
