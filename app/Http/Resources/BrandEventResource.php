<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $brand = $this->brand;

        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'event_id' => $this->event_id,
            'booth_number' => $this->booth_number,
            'booth_size' => $this->booth_size,
            'booth_price' => $this->booth_price,
            'booth_type' => $this->booth_type?->value,
            'booth_type_label' => $this->booth_type?->label(),
            'sales_id' => $this->sales_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'promotion_post_limit' => $this->promotion_post_limit,
            'custom_fields' => $this->custom_fields,
            'order_column' => $this->order_column,

            // Brand details
            'brand' => [
                'id' => $brand->id,
                'ulid' => $brand->ulid,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'company_name' => $brand->company_name,
                'company_address' => $brand->company_address,
                'company_email' => $brand->company_email,
                'company_phone' => $brand->company_phone,
                'custom_fields' => $brand->custom_fields,
                'status' => $brand->status,
                'visibility' => $brand->visibility,
                'brand_logo' => $brand->relationLoaded('media') ? $brand->brand_logo : null,
                'business_categories' => $brand->relationLoaded('tags') ? $brand->business_categories_list : [],
            ],

            'sales' => $this->whenLoaded('sales', fn () => [
                'id' => $this->sales->id,
                'name' => $this->sales->name,
                'email' => $this->sales->email,
            ]),

            'members' => $brand->relationLoaded('users') ? $brand->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_name' => $user->company_name,
                'role' => $user->pivot->role,
                'avatar' => $user->relationLoaded('media') ? $user->getMediaUrls('profile_image') : null,
            ]) : [],

            'promotion_posts_count' => $this->whenLoaded('promotionPosts', fn () => $this->promotionPosts->count()),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
