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
        if ($request->routeIs('posts.index') || $request->routeIs('posts.trash')) {
            return [
                'id' => $this->id,
                'ulid' => $this->ulid,
                'title' => $this->title,
                'slug' => $this->slug,
                'excerpt' => $this->excerpt,
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
                'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                    'id' => $author->id,
                    'name' => $author->name,
                    'email' => $author->email,
                    'role' => $author->pivot->role ?? null,
                    'order' => $author->pivot->order ?? 0,
                ])),
                'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'deleted_at' => $this->deleted_at,
            ];
        }

        // For detail view (show, edit) - complete data
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'content_format' => $this->content_format,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'og_image' => $this->hasMedia('og_image')
                ? $this->getMediaUrls('og_image')
                : null,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'published_at' => $this->published_at,
            'featured' => $this->featured,
            'reading_time' => $this->reading_time,
            'visits_count' => $this->visits_count ?? 0,
            'settings' => $this->settings,
            'source' => $this->source,
            'featured_image' => $this->hasMedia('featured_image')
                ? $this->getMediaUrls('featured_image')
                : $this->featured_image,
            'created_by' => $this->created_by,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                'id' => $author->id,
                'name' => $author->name,
                'email' => $author->email,
                'role' => $author->pivot->role ?? null,
                'order' => $author->pivot->order ?? 0,
            ])),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
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
                return $this->getMediaUrls('featured_image');
            }
        }

        return $this->featured_image;
    }
}
