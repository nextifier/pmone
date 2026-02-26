<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandEventIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $brand = $this->brand;

        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'event_id' => $this->event_id,
            'brand_name' => $brand->name,
            'brand_slug' => $brand->slug,
            'company_name' => $brand->company_name,
            'booth_number' => $this->booth_number,
            'booth_size' => $this->booth_size,
            'booth_price' => $this->booth_price,
            'booth_type' => $this->booth_type?->value,
            'booth_type_label' => $this->booth_type?->label(),
            'status' => $this->status,
            'order_column' => $this->order_column,
            'brand_logo' => $brand->relationLoaded('media') ? $brand->brand_logo : null,
            'business_categories' => $brand->relationLoaded('tags') ? $brand->business_categories_list : [],
            'promotion_posts_count' => $this->promotion_posts_count ?? 0,
            'sales' => $this->whenLoaded('sales', fn () => [
                'id' => $this->sales->id,
                'name' => $this->sales->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
