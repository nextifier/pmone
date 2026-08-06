<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Form;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
use App\Models\LinkPage;
use App\Models\LinkPageBanner;
use App\Models\LinkPageItem;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectBanner;
use App\Models\PromotionPost;
use App\Models\RoomType;
use App\Models\RundownItem;
use App\Models\Ticket;
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
            // BrandEvent carries the per-edition media (dynamic collections from
            // the event's custom fields) that the public brand payloads render.
            BrandEvent::class,
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
            // Added 6 Aug 2026. Every one of these owns media that a cached
            // public payload renders, and every one was silently returning []:
            // a poster replaced through the generic /media/* endpoints, or a
            // queued conversion finishing after the controller's own clear,
            // invalidated NOTHING. Ticket is how a replaced ticket poster
            // survived for hours. Keep this list in step with
            // tests/Feature/MediaResponseCacheTagsCoverageTest.php.
            Ticket::class => ['tickets'],
            Program::class => ['programs'],
            ProjectBanner::class => ['banners'],
            Form::class => ['forms-public'],
            default => [],
        };
    }
}
