<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectBannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'placement' => $this->placement,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'link' => $this->link,
            'cta_label' => $this->cta_label,
            'aspect_ratio' => $this->aspect_ratio,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'start_time' => $this->start_time?->toISOString(),
            'end_time' => $this->end_time?->toISOString(),
            'more_details' => $this->more_details,
            'settings' => $this->settings,
            'image' => $this->image,
            // Since the banner was created, from the permanent daily rollup. A
            // campaign sold on three months used to report only the tail of it,
            // because the raw table is pruned to 90 days.
            'lifetime_impressions' => (int) ($this->lifetime_impressions ?? 0),
            'lifetime_clicks' => (int) ($this->lifetime_clicks ?? 0),
            // @deprecated Rows inside the 90-day retention window.
            'clicks_count' => $this->clicks_count ?? 0,
            'impressions_count' => $this->impressions_count ?? 0,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
