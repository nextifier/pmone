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
                'visits_count' => $this->visits_count ?? $this->visits()->count(),
                'featured_image' => $this->hasMedia('featured_image')
                    ? $this->getMediaUrls('featured_image')
                    : $this->featured_image,
                'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
                'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                    'id' => $author->id,
                    'name' => $author->name,
                    'email' => $author->email,
                    'role' => $author->pivot->role,
                    'order' => $author->pivot->order,
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
            'visits_count' => $this->visits_count ?? $this->visits()->count(),
            'settings' => $this->settings,
            'source' => $this->source,
            'featured_image' => $this->hasMedia('featured_image')
                ? $this->getMediaUrls('featured_image')
                : $this->featured_image,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                'id' => $author->id,
                'name' => $author->name,
                'email' => $author->email,
                'role' => $author->pivot->role,
                'order' => $author->pivot->order,
            ])),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
