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
                'view_count' => $this->view_count,
                'featured_image' => $this->when(
                    $this->hasMedia('featured_image'),
                    $this->getMediaUrls('featured_image')
                ),
                'primary_author' => $this->whenLoaded('primaryAuthor', fn () => new UserMinimalResource($this->primaryAuthor)),
                'authors_count' => $this->whenLoaded('authors', fn () => $this->authors->count()),
                'categories_count' => $this->whenLoaded('categories', fn () => $this->categories->count()),
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
            'og_image' => $this->og_image,
            'og_type' => $this->og_type,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'published_at' => $this->published_at,
            'featured' => $this->featured,
            'reading_time' => $this->reading_time,
            'view_count' => $this->view_count,
            'settings' => $this->settings,
            'source' => $this->source,
            'source_id' => $this->source_id,
            'featured_image' => $this->when(
                $this->hasMedia('featured_image'),
                $this->getMediaUrls('featured_image')
            ),
            'authors' => $this->whenLoaded('authors', fn () => UserMinimalResource::collection($this->authors)),
            'categories' => $this->whenLoaded('categories', fn () => CategoryResource::collection($this->categories)),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
