<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkPageItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'link_page_id' => $this->link_page_id,
            'label' => $this->label,
            'url' => $this->url,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            // Clicks since the item was added, from the permanent daily rollup.
            'lifetime_clicks' => (int) ($this->lifetime_clicks ?? 0),
            // @deprecated Rows inside the 90-day retention window.
            'clicks_count' => $this->clicks_count ?? 0,
            'poster' => $this->poster,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
