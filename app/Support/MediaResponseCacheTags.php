<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
use App\Models\LinkPage;
use App\Models\LinkPageBanner;
use App\Models\LinkPageItem;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Models\RoomType;
use App\Models\RundownItem;
use App\Models\User;

/**
 * Maps a media-owning model class to the response-cache tags its media is
 * rendered under in cached public payloads.
 *
 * Single source of truth for two consumers that would otherwise drift:
 * MediaController::clearOwnerResponseCache (generic /media/* endpoints bypass
 * the owner's Eloquent events) and ClearResponseCacheOnConversionCompleted
 * (queued conversions finish after the controller's clear already ran).
 */
class MediaResponseCacheTags
{
    /**
     * @return string[]
     */
    public static function for(string $modelType): array
    {
        return match ($modelType) {
            Hotel::class,
            RoomType::class,
            HotelTransferOption::class => ['hotels'],
            Brand::class => ['brands'],
            PromotionPost::class => ['brands', 'promotion-posts'],
            Partner::class => ['partners'],
            Guest::class => ['guests'],
            // Event media spans the gallery collection AND the poster_image /
            // visitor_eguide embedded in cached event payloads; poster_image
            // is also embedded in the cached brand-detail payload
            // (PublicBrandDetailResource::getEventPoster).
            Event::class => ['gallery', 'events', 'brands'],
            // Project profile_image is embedded in every cached event payload
            // (EventResource) besides the project profile itself. OG media is
            // embedded in the cached website-settings og_pages payload
            // (PublicProjectController::ogPagesPayload).
            Project::class => ['projects', 'events', 'website-settings'],
            Post::class => ['blog-posts'],
            // RundownItem poster_image (incl. its caption/alt custom
            // properties) is embedded in the cached rundown payload
            // (RundownItemPublicResource).
            RundownItem::class => ['rundown'],
            // User profile_image is embedded in cached blog author bylines
            // (PostResource list view -> UserMinimalResource) and the
            // /resolve profile. Mirrors User::clearPublicProfileResponseCache.
            User::class => ['short-links', 'blog-posts'],
            LinkPage::class,
            LinkPageItem::class,
            LinkPageBanner::class => ['short-links'],
            default => [],
        };
    }
}
