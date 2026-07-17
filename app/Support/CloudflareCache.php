<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Purges the Cloudflare edge cache for the zone. Purely a network call wrapper -
 * the decision of WHEN to purge lives in the caller (see
 * PurgeCloudflareCacheOnResponseCacheCleared), not here.
 *
 * Everything is purged rather than individual URLs because purging by cache tag
 * or prefix needs an Enterprise plan, and the cached list endpoints
 * (`blog/posts?author=…&page=…`) fan out into too many URLs to enumerate.
 */
class CloudflareCache
{
    protected const PURGE_URL = 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache';

    /**
     * Whether a zone and a token are both configured. Callers check this before
     * queueing work so that local and CI environments stay off the network.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.cloudflare.zone_id'))
            && filled(config('services.cloudflare.purge_token'));
    }

    /**
     * Purge every cached response for the zone. Returns false for missing
     * config, a non-2xx/transport failure, or a Cloudflare-reported failure -
     * never throws, so a flaky Cloudflare call cannot fail the job that owns it.
     * A failed purge only means the edge serves stale content until the TTL
     * lapses, so it is logged rather than retried into a purge storm.
     */
    public static function purgeEverything(): bool
    {
        if (! static::isConfigured()) {
            return false;
        }

        $zoneId = config('services.cloudflare.zone_id');

        try {
            $response = Http::withToken(config('services.cloudflare.purge_token'))
                ->timeout(10)
                ->connectTimeout(5)
                ->post(sprintf(self::PURGE_URL, $zoneId), ['purge_everything' => true]);

            if ($response->successful() && (bool) $response->json('success', false)) {
                return true;
            }

            Log::warning('Cloudflare cache purge rejected', [
                'status' => $response->status(),
                'errors' => $response->json('errors'),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('Cloudflare cache purge failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
