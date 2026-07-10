<?php

namespace App\Http\Resources;

use App\Services\Brand\BrandProfileScoreService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicBrandIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $brand = $this->brand;

        $profile = app(BrandProfileScoreService::class)->score($this->resource);

        // Avatar shown on the public exhibitors list. During the migration
        // window (before brands:copy-logo-to-profile-image runs), fall back to
        // the legacy brand_logo media so sites keep an avatar. `brand_logo` is
        // kept as a DEPRECATED alias so the 11 event sites (deployed
        // non-atomically) don't break; remove it once all sites read
        // profile_image.
        $profileImage = $brand?->relationLoaded('media')
            ? ($brand->profile_image ?? $brand->getMediaUrls('brand_logo'))
            : null;

        return [
            'id' => $brand?->id,
            'brand_event_id' => $this->id,
            'slug' => $brand?->slug,
            'brand_name' => $brand?->name,
            'company_name' => $brand?->company_name,
            'profile_image' => $profileImage,
            'brand_logo' => $profileImage,
            'business_categories' => $brand?->relationLoaded('tags')
                ? $brand->business_categories_list
                : [],
            'booth_number' => $this->booth_number,
            'links' => $this->getActiveLinks(),
            'promotions' => $this->getPromotionPreviews(),
            'score' => $profile['score'],
            'score_breakdown' => $profile['breakdown'],
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
