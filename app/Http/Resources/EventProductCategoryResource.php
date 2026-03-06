<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventProductCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'catalog_files' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('catalog_files'),
                fn () => $this->getMediaUrls('catalog_files')
            ),
            'description_images' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('description_images'),
                fn () => $this->getMediaUrls('description_images')
            ),
            'products' => EventProductResource::collection($this->whenLoaded('products')),
            'order_column' => $this->order_column,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
