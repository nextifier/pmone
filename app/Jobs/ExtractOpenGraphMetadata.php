<?php

namespace App\Jobs;

use App\Models\ShortLink;
use App\Services\OpenGraph\OpenGraphExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExtractOpenGraphMetadata implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $shortLinkId,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(OpenGraphExtractor $extractor): void
    {
        $shortLink = ShortLink::find($this->shortLinkId);

        if (! $shortLink) {
            Log::warning('ShortLink not found for OG extraction', [
                'short_link_id' => $this->shortLinkId,
            ]);

            return;
        }

        try {
            $metadata = $extractor->extract($shortLink->destination_url);

            // Update short link with extracted metadata
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
        } catch (Throwable $e) {
            Log::error('Failed to extract OpenGraph metadata', [
                'short_link_id' => $shortLink->id,
                'slug' => $shortLink->slug,
                'destination_url' => $shortLink->destination_url,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to allow retry
        }
    }
}
