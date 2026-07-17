<?php

namespace App\Listeners;

use App\Jobs\PurgeCloudflareCache;
use App\Support\CloudflareCache;
use Spatie\ResponseCache\Events\ClearedResponseCacheEvent;

/**
 * Mirrors every origin response-cache clear onto the Cloudflare edge.
 *
 * Hooking the event rather than the ~109 ResponseCache::clear() call sites means
 * new call sites are covered for free, and none can be forgotten. The event
 * carries no tags, so the whole zone is purged - see CloudflareCache for why
 * per-tag purging is not an option on this plan.
 */
class PurgeCloudflareCacheOnResponseCacheCleared
{
    public function handle(ClearedResponseCacheEvent $event): void
    {
        if (! CloudflareCache::isConfigured()) {
            return;
        }

        PurgeCloudflareCache::dispatch()
            ->delay(now()->addSeconds(PurgeCloudflareCache::DEBOUNCE_SECONDS));
    }
}
