<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicBrandIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $brand = $this->brand;

        return [
            'slug' => $brand?->slug,
            'brand_name' => $brand?->name,
            'company_name' => $brand?->company_name,
            'brand_logo' => $brand?->relationLoaded('media') ? $brand->brand_logo : null,
            'business_categories' => $brand?->relationLoaded('tags')
                ? $brand->business_categories_list
                : [],
            'booth_number' => $this->booth_number,
            'links' => $this->getActiveLinks(),
            'promotions' => $this->getPromotionPreviews(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function getActiveLinks(): array
    {
        $brand = $this->brand;

        if (! $brand || ! $brand->relationLoaded('links')) {
            return [];
        }

        return $brand->links
            ->where('is_active', true)
            ->sortBy('order')
            ->map(fn ($link) => [
                'label' => $link->label,
                'url' => $link->url,
            ])
            ->values()
            ->toArray();
    }

    protected function getPromotionPreviews(): array
    {
        if (! $this->relationLoaded('promotionPosts')) {
            return [];
        }

        return $this->promotionPosts
            ->filter(fn ($post) => $post->relationLoaded('media') && $post->hasMedia('post_image'))
            ->flatMap(fn ($post) => $post->post_images)
            ->take(3)
            ->values()
            ->toArray();
    }
}
