<?php

use App\Jobs\PurgeCloudflareCache;
use App\Support\CloudflareCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * The zone's cache rules give the public API a one hour edge TTL, so an origin
 * response-cache clear that is not mirrored onto Cloudflare leaves the event
 * websites serving content the app already invalidated.
 */
function configureCloudflare(): void
{
    config([
        'services.cloudflare.zone_id' => 'zone-123',
        'services.cloudflare.purge_token' => 'token-abc',
    ]);
}

it('stays off the network when cloudflare is not configured', function () {
    config(['services.cloudflare.zone_id' => null, 'services.cloudflare.purge_token' => null]);
    Http::preventStrayRequests();
    Queue::fake();

    ResponseCache::clear(['blog-posts']);

    expect(CloudflareCache::isConfigured())->toBeFalse();
    Queue::assertNothingPushed();
});

it('needs both a zone and a token before it will purge', function (?string $zone, ?string $token) {
    config(['services.cloudflare.zone_id' => $zone, 'services.cloudflare.purge_token' => $token]);
    Http::preventStrayRequests();

    expect(CloudflareCache::isConfigured())->toBeFalse()
        ->and(CloudflareCache::purgeEverything())->toBeFalse();
})->with([
    'neither' => [null, null],
    'zone only' => ['zone-123', null],
    'token only' => [null, 'token-abc'],
]);

it('queues a purge when the origin response cache is cleared', function () {
    configureCloudflare();
    Queue::fake();

    ResponseCache::clear(['blog-posts']);

    Queue::assertPushed(PurgeCloudflareCache::class, 1);
});

it('is unique so a burst of clears cannot stack up zone purges', function () {
    // One editor action fans out into several clears (a controller, then the
    // OG-image job), and bulk operations into many more. ShouldBeUnique is what
    // stops each one becoming its own purge; Queue::fake() does not enforce it,
    // so assert the contract itself.
    expect(new PurgeCloudflareCache)->toBeInstanceOf(ShouldBeUnique::class);
});

it('delays the purge so a burst of clears can coalesce', function () {
    configureCloudflare();
    Queue::fake();

    ResponseCache::clear(['blog-posts']);

    Queue::assertPushed(function (PurgeCloudflareCache $job) {
        return $job->delay !== null;
    });
});

it('purges the whole zone with the configured token', function () {
    configureCloudflare();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => []], 200),
    ]);

    expect(CloudflareCache::purgeEverything())->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-123/purge_cache'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer token-abc')
            && $request['purge_everything'] === true;
    });
});

it('reports failure without throwing when cloudflare rejects the purge', function () {
    configureCloudflare();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'nope']]], 403),
    ]);

    // A failed purge only means stale edge content until the TTL lapses, so it
    // must never bubble up and fail the job that owns it.
    expect(CloudflareCache::purgeEverything())->toBeFalse();
});

it('reports failure without throwing when the request blows up', function () {
    configureCloudflare();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(CloudflareCache::purgeEverything())->toBeFalse();
});
