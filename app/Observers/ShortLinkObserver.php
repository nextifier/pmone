<?php

namespace App\Observers;

use App\Jobs\ExtractOpenGraphMetadata;
use App\Models\ShortLink;

class ShortLinkObserver
{
    /**
     * Handle the ShortLink "created" event.
     */
    public function created(ShortLink $shortLink): void
    {
        // Dispatch job to extract OpenGraph metadata asynchronously
        ExtractOpenGraphMetadata::dispatch($shortLink->id);
    }

    /**
     * Handle the ShortLink "updating" event.
     */
    public function updating(ShortLink $shortLink): void
    {
        // Only re-extract if destination_url changed
        if ($shortLink->isDirty('destination_url')) {
            ExtractOpenGraphMetadata::dispatch($shortLink->id);
        }
    }
}
