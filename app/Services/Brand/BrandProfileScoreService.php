<?php

namespace App\Services\Brand;

use App\Models\Brand;
use App\Models\BrandEvent;

/**
 * Computes a brand's profile-completeness score (0-100) within the context of a
 * single event. Global brand data (logo, description, links, categories, company
 * info) is shared across events; the promotion components are per brand-event.
 *
 * Each check is dual-source: it prefers an already-loaded relation, falls back to
 * a withCount aggregate, and finally to a lazy query. This keeps the score free of
 * N+1 queries when controllers eager-load the right relations/counts.
 */
class BrandProfileScoreService
{
    /**
     * Scoring rubric. Weights sum to 100.
     *
     * @var list<array{key: string, label: string, weight: int}>
     */
    private const RUBRIC = [
        ['key' => 'logo', 'label' => 'Profile Image', 'weight' => 18],
        ['key' => 'description', 'label' => 'Description', 'weight' => 14],
        ['key' => 'promotion_image', 'label' => 'Promo Image', 'weight' => 14],
        ['key' => 'links', 'label' => 'Social Links', 'weight' => 12],
        ['key' => 'categories', 'label' => 'Business Categories', 'weight' => 10],
        ['key' => 'promotion_caption', 'label' => 'Promo Caption', 'weight' => 8],
        ['key' => 'company_name', 'label' => 'Company Name', 'weight' => 6],
        ['key' => 'company_email', 'label' => 'Company Email', 'weight' => 6],
        ['key' => 'company_phone', 'label' => 'Company Phone', 'weight' => 6],
        ['key' => 'company_address', 'label' => 'Company Address', 'weight' => 6],
    ];

    /**
     * @return array{score: int, is_complete: bool, breakdown: list<array{key: string, label: string, weight: int, filled: bool}>}
     */
    public function score(BrandEvent $brandEvent): array
    {
        $brand = $brandEvent->brand;

        $linksCount = $this->activeLinksCount($brand);

        $filled = [
            'logo' => $this->hasLogo($brand),
            'description' => $this->hasDescription($brand),
            'promotion_image' => $this->promotionPostsWithImageCount($brandEvent) > 0,
            'links' => $linksCount >= 1,
            'categories' => $this->categoriesCount($brand) >= 1,
            'promotion_caption' => $this->promotionPostsWithCaptionCount($brandEvent) > 0,
            'company_name' => filled($brand?->company_name),
            'company_email' => filled($brand?->company_email),
            'company_phone' => filled($brand?->company_phone),
            'company_address' => $this->hasAddress($brand),
        ];

        $score = 0;
        $breakdown = [];

        foreach (self::RUBRIC as $item) {
            $key = $item['key'];
            $weight = $item['weight'];

            if ($key === 'links') {
                // Tiered: half the weight for the first link, full at two or more.
                $earned = match (true) {
                    $linksCount >= 2 => $weight,
                    $linksCount === 1 => (int) floor($weight / 2),
                    default => 0,
                };
            } else {
                $earned = $filled[$key] ? $weight : 0;
            }

            $score += $earned;

            $breakdown[] = [
                'key' => $key,
                'label' => $item['label'],
                'weight' => $weight,
                'filled' => $filled[$key],
            ];
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'is_complete' => $score >= 100,
            'breakdown' => $breakdown,
        ];
    }

    private function hasLogo(?Brand $brand): bool
    {
        if (! $brand) {
            return false;
        }

        // Transitional: accept either the new profile_image (avatar) or the
        // legacy brand_logo until brands:copy-logo-to-profile-image has run
        // in production. Rubric key stays 'logo' so score sorting on the
        // event sites is unaffected.
        if ($brand->relationLoaded('media')) {
            return $brand->getMedia('profile_image')->isNotEmpty()
                || $brand->getMedia('brand_logo')->isNotEmpty();
        }

        return $brand->hasMedia('profile_image') || $brand->hasMedia('brand_logo');
    }

    private function hasDescription(?Brand $brand): bool
    {
        return filled(trim(strip_tags((string) $brand?->description)));
    }

    /**
     * The address is a JSONB object, so an array of empty strings still counts
     * as unfilled. At least one sub-field must carry a value.
     */
    private function hasAddress(?Brand $brand): bool
    {
        return collect($brand?->address ?? [])->contains(fn ($value) => filled($value));
    }

    private function activeLinksCount(?Brand $brand): int
    {
        if (! $brand) {
            return 0;
        }

        if ($brand->relationLoaded('links')) {
            return $brand->links->where('is_active', true)->count();
        }

        return $brand->links()->where('is_active', true)->count();
    }

    private function categoriesCount(?Brand $brand): int
    {
        if (! $brand) {
            return 0;
        }

        return count($brand->business_categories_list);
    }

    private function promotionPostsWithImageCount(BrandEvent $brandEvent): int
    {
        if (isset($brandEvent->posts_with_image_count)) {
            return (int) $brandEvent->posts_with_image_count;
        }

        if ($brandEvent->relationLoaded('promotionPosts')) {
            return $brandEvent->promotionPosts
                ->filter(fn ($post) => $post->hasMedia('post_image'))
                ->count();
        }

        return $brandEvent->promotionPosts()
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'post_image'))
            ->count();
    }

    private function promotionPostsWithCaptionCount(BrandEvent $brandEvent): int
    {
        if (isset($brandEvent->posts_with_caption_count)) {
            return (int) $brandEvent->posts_with_caption_count;
        }

        if ($brandEvent->relationLoaded('promotionPosts')) {
            return $brandEvent->promotionPosts
                ->filter(fn ($post) => filled(trim((string) $post->caption)))
                ->count();
        }

        return $brandEvent->promotionPosts()
            ->whereNotNull('caption')
            ->where('caption', '!=', '')
            ->count();
    }
}
