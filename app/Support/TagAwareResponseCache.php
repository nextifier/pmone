<?php

namespace App\Support;

use App\Jobs\PurgeEdgeCache;
use Spatie\ResponseCache\ResponseCache;

/**
 * Mirrors every origin response-cache clear onto the event websites' Cloudflare
 * edge cache, carrying the TAGS along.
 *
 * Why a decorator instead of a listener: Spatie fires ClearedResponseCacheEvent
 * with no payload at all (the class is literally empty), so a listener knows
 * that something was cleared but not what. Purging by URL needs the tags.
 * Overriding clear() here is the one place that sees them, and because
 * ResponseCache is resolved from the container as 'responsecache', binding this
 * subclass covers all ~109 existing call sites — models, controllers, importers
 * — without editing any of them, and covers future ones for free.
 *
 * Registered in AppServiceProvider::boot().
 *
 * This is the SAFETY NET: it only knows tags, so it purges list pages and API
 * endpoints. Detail pages need their slug, which only the changed model knows —
 * that path runs through App\Traits\ClearsResponseCache.
 */
class TagAwareResponseCache extends ResponseCache
{
    public function clear(array $tags = []): bool
    {
        $result = parent::clear($tags);

        if ($tags !== [] && EdgeCache::isConfigured()) {
            PurgeEdgeCache::dispatch($tags)
                ->delay(now()->addSeconds(PurgeEdgeCache::DEBOUNCE_SECONDS));
        }

        return $result;
    }
}
