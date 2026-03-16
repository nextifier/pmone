<?php

namespace App\Observers;

use App\Jobs\ExtractLinkPageItemMetadata;
use App\Models\LinkPageItem;

class LinkPageItemObserver
{
    public function created(LinkPageItem $item): void
    {
        if ($item->url) {
            ExtractLinkPageItemMetadata::dispatch($item->id);
        }
    }

    public function updating(LinkPageItem $item): void
    {
        if ($item->isDirty('url') && $item->url) {
            ExtractLinkPageItemMetadata::dispatch($item->id);
        }
    }
}
