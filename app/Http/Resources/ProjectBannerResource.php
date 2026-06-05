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
            'clicks_count' => $this->clicks_count ?? 0,
            'impressions_count' => $this->impressions_count ?? 0,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
