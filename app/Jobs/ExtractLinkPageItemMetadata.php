<?php

namespace App\Jobs;

use App\Models\LinkPageItem;
use App\Services\OpenGraph\OpenGraphExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExtractLinkPageItemMetadata implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $linkPageItemId,
    ) {}

    public function handle(OpenGraphExtractor $extractor): void
    {
        $item = LinkPageItem::find($this->linkPageItemId);

        if (! $item) {
            Log::warning('LinkPageItem not found for OG extraction', [
                'link_page_item_id' => $this->linkPageItemId,
            ]);

            return;
        }

        try {
            $metadata = $extractor->extract($item->url);

            $item->updateQuietly([
                'og_title' => $metadata['og_title'],
                'og_description' => $metadata['og_description'],
                'og_image' => $metadata['og_image'],
            ]);

            Log::info('LinkPageItem OG metadata extracted successfully', [
                'link_page_item_id' => $item->id,
                'label' => $item->label,
                'has_og_title' => ! empty($metadata['og_title']),
                'has_og_image' => ! empty($metadata['og_image']),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to extract LinkPageItem OG metadata', [
                'link_page_item_id' => $item->id,
                'url' => $item->url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
