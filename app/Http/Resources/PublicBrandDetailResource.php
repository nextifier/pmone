<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PublicBrandDetailResource extends PublicBrandIndexResource
{
    public function toArray(Request $request): array
    {
        $brand = $this->brand;

        return array_merge(parent::toArray($request), [
            'brand_description' => $brand?->description,
            'promotions' => $this->getFullPromotions(),
        ]);
    }

    private function getFullPromotions(): array
    {
        if (! $this->relationLoaded('promotionPosts')) {
            return [];
        }

        return $this->promotionPosts->map(fn ($post) => [
            'image' => $post->relationLoaded('media') ? $post->post_image : null,
            'caption' => $post->caption,
            'created_at' => $post->created_at?->toISOString(),
        ])->values()->toArray();
    }
}
