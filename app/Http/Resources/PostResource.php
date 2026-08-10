<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Image variants the public blog listing actually renders.
     *
     * PostCard.vue reads `md.url`, `sm.url`, `original`, `lqip.url` and the
     * `md` dimensions; PostRelated.vue reads `md.url` only. `lg` and `xl` are
     * never touched, and at 50 posts a page they were 39% of the payload —
     * measured 11 Aug 2026 at 42,879 B of a 111,128 B response.
     *
     * Only the PUBLIC list is trimmed. The admin table shares this branch and
     * the detail branch is untouched, so nothing in the dashboard or on the
     * article page changes.
     */
    private const PUBLIC_LIST_IMAGE_KEYS = [
        'url', 'original', 'caption', 'alt', 'width', 'height', 'lqip', 'sm', 'md',
    ];

    /** Author fields a byline needs. `sm` is the avatar; `original` its fallback. */
    private const PUBLIC_LIST_AVATAR_KEYS = ['url', 'original', 'alt', 'sm'];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For list view (index, trash) - minimal data
        // Check for internal admin routes OR public API list endpoints
        // Public API list endpoints: /posts, /posts/featured, /posts/search, /categories/*/posts, /tags/*/posts, /authors/*/posts
        $isPublicApiListView = $request->is('api/public/blog/*') && (
            $request->is('api/public/blog/posts') ||
            $request->is('api/public/blog/posts/featured') ||
            $request->is('api/public/blog/posts/search') ||
            $request->is('api/public/blog/categories/*/posts') ||
            $request->is('api/public/blog/tags/*/posts') ||
            $request->is('api/public/blog/authors/*/posts')
        );

        $isListView = $request->routeIs('posts.index') ||
            $request->routeIs('posts.trash') ||
            $isPublicApiListView;

        if ($isListView) {
            return [
                'id' => $this->id,
                'ulid' => $this->ulid,
                'title' => $this->translated('title'),
                'slug' => $this->slug,
                'excerpt' => $this->translated('excerpt'),
                'status' => $this->status,
                'visibility' => $this->visibility,
                'published_at' => $this->published_at,
                'featured' => $this->featured,
                'reading_time' => $this->reading_time,
                'visits_count' => $this->visits_count ?? 0,
                'media_count' => $this->media_count ?? 0,
                'featured_image' => $isPublicApiListView
                    ? self::keepMediaVariants($this->getFeaturedImageFromLoadedMedia(), self::PUBLIC_LIST_IMAGE_KEYS)
                    : $this->getFeaturedImageFromLoadedMedia(),
                'created_by' => $this->created_by,
                'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
                'authors' => $this->whenLoaded('authors', fn () => $isPublicApiListView
                    ? $this->authors->map(fn ($author) => self::publicListAuthor($author))
                    : UserMinimalResource::collection($this->authors)),
                'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
                'settings' => $this->settings,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'deleted_at' => $this->deleted_at,
            ];
        }

        // For detail view (show, edit) and public API - complete data
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->translated('title'),
            'slug' => $this->slug,
            'excerpt' => $this->translated('excerpt'),
            'content' => $this->injectContentImageLqip($this->translated('content')),
            'content_format' => $this->content_format,
            'meta_title' => $this->translated('meta_title'),
            'meta_description' => $this->translated('meta_description'),
            $this->mergeWhen(! $request->is('api/public/*'), fn () => [
                'title_translations' => $this->getTranslations('title'),
                'excerpt_translations' => $this->getTranslations('excerpt'),
                'content_translations' => collect($this->getTranslations('content'))
                    ->map(fn ($html) => $this->injectContentImageLqip($html))
                    ->all(),
                'meta_title_translations' => $this->getTranslations('meta_title'),
                'meta_description_translations' => $this->getTranslations('meta_description'),
            ]),
            'og_image' => $this->effectiveOgImageUrl(),
            $this->mergeWhen(! $request->is('api/public/*'), fn () => [
                'og_image_source' => $this->hasMedia('og_image') ? 'manual'
                    : ($this->hasMedia('og_image_generated') ? 'generated' : null),
            ]),
            'status' => $this->status,
            'visibility' => $this->visibility,
            'published_at' => $this->published_at,
            'featured' => $this->featured,
            'reading_time' => $this->reading_time,
            'visits_count' => $this->visits_count ?? 0,
            'settings' => $this->settings,
            'source' => $this->source,
            'featured_image' => $this->getFeaturedImageFromLoadedMedia(),
            'created_by' => $this->created_by,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'primaryAuthor' => $this->whenLoaded('primaryAuthor', fn () => new UserMinimalResource($this->primaryAuthor)),
            'authors' => $this->whenLoaded('authors', fn () => UserMinimalResource::collection($this->authors)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    /**
     * Resolve a translatable field to the current locale via the global
     * fallback chain (requested -> en -> any filled locale).
     */
    private function translated(string $field): ?string
    {
        return $this->getTranslation($field, app()->getLocale()) ?: null;
    }

    /**
     * Get featured image from already loaded media relationship to avoid N+1
     */
    private function getFeaturedImageFromLoadedMedia(): mixed
    {
        // Check if media relationship is loaded
        if ($this->relationLoaded('media')) {
            $featuredMedia = $this->media->firstWhere('collection_name', 'featured_image');

            if ($featuredMedia) {
                return $this->getMediaUrlsDetailed('featured_image');
            }
        }

        return $this->featured_image;
    }

    /**
     * Inject data-lqip attributes into content image tags.
     * Derives LQIP URL directly from the src URL pattern.
     */
    private function injectContentImageLqip(?string $content): ?string
    {
        if (! $content) {
            return $content;
        }

        return preg_replace_callback('/<img([^>]+)>/', function ($match) {
            $imgTag = $match[0];
            $attrs = $match[1];

            // Skip if already has data-lqip
            if (str_contains($attrs, 'data-lqip')) {
                return $imgTag;
            }

            // Extract src attribute
            if (! preg_match('/src="([^"]+)"/', $attrs, $srcMatch)) {
                return $imgTag;
            }

            $src = $srcMatch[1];

            // Skip external images (not from our CDN/storage)
            if (! str_contains($src, 'content_images')) {
                return $imgTag;
            }

            $lqipUrl = $this->deriveLqipUrl($src);

            if (! $lqipUrl) {
                return $imgTag;
            }

            $lqipAttr = sprintf(' data-lqip="%s"', htmlspecialchars($lqipUrl, ENT_QUOTES, 'UTF-8'));

            return '<img'.$attrs.$lqipAttr.'>';
        }, $content);
    }

    /**
     * Derive LQIP URL from content image src URL pattern.
     * Conversion URLs: .../conversions/{name}-lg.jpg -> .../conversions/{name}-lqip.jpg
     * Original URLs: .../{name}.jpg -> .../conversions/{name}-lqip.jpg
     */
    private function deriveLqipUrl(string $src): ?string
    {
        // New format: conversion URL (e.g. /conversions/image-lg.jpg?v=...)
        if (preg_match('/^(.+\/conversions\/.+)-(sm|md|lg|xl)(\.[^.?]+)(.*)$/', $src, $match)) {
            return $match[1].'-lqip'.$match[3].$match[4];
        }

        // Old format: original URL (e.g. /image.jpg?v=...)
        if (preg_match('/^(.+\/content_images\/\d+)\/([^\/]+?)(\.[^.?]+)(.*)$/', $src, $match)) {
            $basePath = $match[1];
            $name = $match[2];
            $queryString = $match[4];

            return $basePath.'/conversions/'.$name.'-lqip.jpg'.$queryString;
        }

        return null;
    }

    /**
     * Drop the media variants a given view never renders.
     *
     * Deliberately filters the array HERE rather than in HasMediaManager: that
     * trait feeds a dozen other resources, and narrowing it globally would be a
     * breaking change for all of them to solve a problem only the public blog
     * listing has.
     *
     * @param  array<string, mixed>|null  $media
     * @param  list<string>  $keep
     * @return array<string, mixed>|null
     */
    private static function keepMediaVariants(?array $media, array $keep): ?array
    {
        if ($media === null) {
            return null;
        }

        return array_intersect_key($media, array_flip($keep));
    }

    /**
     * A byline-sized author for the public listing.
     *
     * UserMinimalResource carries `title`, `pivot` and a full profile-image
     * conversion set, none of which PostCard.vue reads. At 50 posts that was
     * 36,500 B — a third of the response — and almost all of it the SAME author
     * object repeated once per post.
     *
     * @return array<string, mixed>
     */
    private static function publicListAuthor(mixed $author): array
    {
        // Mirrors UserMinimalResource: only read media that is already loaded,
        // so a listing can never turn into an N+1.
        $profileImage = $author->relationLoaded('media')
            ? self::keepMediaVariants($author->getMediaUrls('profile_image'), self::PUBLIC_LIST_AVATAR_KEYS)
            : null;

        return [
            'id' => $author->id,
            'name' => $author->name,
            'username' => $author->username,
            'profile_image' => $profileImage,
        ];
    }
}
