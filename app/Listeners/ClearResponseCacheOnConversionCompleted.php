<?php

namespace App\Listeners;

use App\Support\MediaResponseCacheTags;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Busts the owner's public response cache when a QUEUED media conversion
 * lands. Controllers clear synchronously right after the upload, but md/lg/xl
 * conversions finish later on the queue and only touch the Media row - no
 * owner Eloquent event fires, so a public hit in between re-caches the
 * original-image fallback (HasMediaManager::getMediaUrls) for the full TTL.
 * Also covers media-library:regenerate backfills, which fire the same event
 * with no owner save at all.
 */
class ClearResponseCacheOnConversionCompleted
{
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        $tags = MediaResponseCacheTags::for($event->media->model_type);

        if ($tags !== []) {
            ResponseCache::clear($tags);
        }
    }
}
