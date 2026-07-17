<?php

namespace App\Listeners;

use Spatie\ResponseCache\Events\ResponseCacheHitEvent;

class MarkResponseCacheHit
{
    /**
     * The request attribute other middleware may read to tell whether the
     * response was served from the response cache.
     */
    public const ATTRIBUTE = 'responsecache.hit';

    /**
     * Flag the request so ValidateApiKey can skip its per-request logging
     * writes. The response cache runs as route middleware, which is *after*
     * the api.key group middleware, so ValidateApiKey cannot otherwise tell a
     * cache hit from real origin work and ends up writing a row for both.
     */
    public function handle(ResponseCacheHitEvent $event): void
    {
        $event->request->attributes->set(self::ATTRIBUTE, true);
    }
}
