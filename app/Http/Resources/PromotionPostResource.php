<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_event_id' => $this->brand_event_id,
            'caption' => $this->caption,
            'custom_fields' => $this->custom_fields,
            'order_column' => $this->order_column,
            'post_image' => $this->relationLoaded('media') ? $this->post_image : null,
            'post_images' => $this->relationLoaded('media') ? $this->post_images : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
