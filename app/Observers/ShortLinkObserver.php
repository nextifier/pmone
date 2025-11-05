<?php

namespace App\Observers;

use App\Models\ShortLink;
use App\Services\OpenGraph\OpenGraphExtractor;
use Illuminate\Support\Facades\Log;

class ShortLinkObserver
{
    public function __construct(
        private OpenGraphExtractor $extractor,
    ) {
    }

    /**
     * Handle the ShortLink "created" event.
     */
    public function created(ShortLink $shortLink): void
    {
        $this->extractOpenGraphMetadata($shortLink);
    }

    /**
     * Handle the ShortLink "updating" event.
     */
    public function updating(ShortLink $shortLink): void
    {
        // Only re-extract if destination_url changed
        if ($shortLink->isDirty('destination_url')) {
            $this->extractOpenGraphMetadata($shortLink);
        }
    }

    /**
     * Extract OpenGraph metadata from destination URL and update short link.
     */
    private function extractOpenGraphMetadata(ShortLink $shortLink): void
    {
        try {
            $metadata = $this->extractor->extract($shortLink->destination_url);

            // Update short link with extracted metadata
            // Use updateQuietly to avoid triggering the observer again
            $shortLink->updateQuietly([
                'og_title' => $metadata['og_title'],
                'og_description' => $metadata['og_description'],
                'og_image' => $metadata['og_image'],
                'og_type' => $metadata['og_type'],
            ]);

            Log::info('OpenGraph metadata extracted successfully', [
                'short_link_id' => $shortLink->id,
                'slug' => $shortLink->slug,
                'has_og_title' => ! empty($metadata['og_title']),
                'has_og_image' => ! empty($metadata['og_image']),
            ]);
        } catch (\Throwable $e) {
            // Don't fail the short link creation if OG extraction fails
            Log::warning('Failed to extract OpenGraph metadata', [
                'short_link_id' => $shortLink->id,
                'slug' => $shortLink->slug,
                'destination_url' => $shortLink->destination_url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
