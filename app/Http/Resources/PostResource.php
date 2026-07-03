<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
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
                'featured_image' => $this->getFeaturedImageFromLoadedMedia(),
                'created_by' => $this->created_by,
                'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
                'authors' => $this->whenLoaded('authors', fn () => UserMinimalResource::collection($this->authors)),
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
            'og_image' => $this->when(
                $this->relationLoaded('media') && $this->hasMedia('og_image'),
                fn () => $this->getMediaUrlsDetailed('og_image')
            ),
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
}
