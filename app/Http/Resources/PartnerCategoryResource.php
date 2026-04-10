<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'no_container' => $this->no_container,
            'order_column' => $this->order_column,
            'partners' => $this->whenLoaded('partners', fn () => $this->partners->map(fn ($partner) => [
                'id' => $partner->id,
                'pivot_id' => $partner->pivot->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'website_url' => $partner->website_url,
                'partner_logo' => $partner->relationLoaded('media') ? $partner->partner_logo : null,
                'order_column' => $partner->pivot->order_column,
            ])),
            'partners_count' => $this->whenCounted('partners'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
