<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For list view (index, trash) - minimal data
        if ($request->routeIs('categories.index') || $request->routeIs('categories.trash')) {
            return [
                'id' => $this->id,
                'ulid' => $this->ulid,
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'parent_id' => $this->parent_id,
                'visibility' => $this->visibility,
                'featured_image' => $this->when(
                    $this->hasMedia('featured_image'),
                    $this->getMediaUrls('featured_image')
                ),
                'posts_count' => $this->whenLoaded('posts', fn () => $this->posts->count()),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'deleted_at' => $this->deleted_at,
            ];
        }

        // For detail view (show, edit) - complete data
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'visibility' => $this->visibility,
            'featured_image' => $this->when(
                $this->hasMedia('featured_image'),
                $this->getMediaUrls('featured_image')
            ),
            'parent' => $this->whenLoaded('parent', fn () => new CategoryResource($this->parent)),
            'children' => $this->whenLoaded('children', fn () => CategoryResource::collection($this->children)),
            'posts' => $this->whenLoaded('posts', fn () => PostResource::collection($this->posts)),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
