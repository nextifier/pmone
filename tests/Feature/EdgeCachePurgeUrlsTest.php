<?php

use App\Support\EdgeCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Locks the shape of the URLs a tag purge sends to Cloudflare.
 *
 * These URLs hand-mirror a cache-key scheme that lives in another repo
 * (pmone-events/layers/base/server/utils/edgeCache.ts, buildEdgeCacheKey), and
 * purge-by-URL is an exact string match — a variant one side emits and the
 * other does not is a cache entry nothing can ever clear. That went untested on
 * both sides until 6 Aug 2026. These tests exist so the next person to touch
 * either side finds out immediately.
 *
 * Current scheme: `?__cm=dark|light` on HTML paths. `?__lc` / `?__al` were
 * dropped when "/" became a prerendered static asset that never reaches the
 * worker.
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

it('expands an html path across both colour-mode variants', function () {
    expect(EdgeCache::htmlVariants('/news', ['en', 'id']))
        ->toBe([
            '/news', '/news?__cm=dark', '/news?__cm=light',
            '/id/news', '/id/news?__cm=dark', '/id/news?__cm=light',
        ]);
});

it('purges every colour-mode variant of an html path', function () {
    // The worker caches its rendered HTML under ?__cm because SSR stamps the
    // colour mode onto <html class>. Purge-by-URL is an exact match, so a
    // variant missing here is an entry that never gets cleared.
    $urls = capturedPurgeUrls(fn () => EdgeCache::purgeTags(['blog-posts'], [], 'ioe'));

    expect($urls)
        ->toContain('https://indooutingexpo.co.id/news?__cm=dark')
        ->toContain('https://indooutingexpo.co.id/news?__cm=light')
        ->toContain('https://indooutingexpo.co.id/id/news?__cm=dark');
});

it('emits no locale-negotiation params, because "/" is a static asset now', function () {
    // __lc / __al keyed the old "/" cache entry. "/" is prerendered and never
    // reaches the worker, so those variants would purge nothing.
    $urls = capturedPurgeUrls(fn () => EdgeCache::purgeTags(['blog-posts'], [], 'ioe'));

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $url) {
        expect($url)->not->toContain('__lc')
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
