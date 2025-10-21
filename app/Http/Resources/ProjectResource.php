<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For list view (index, trash) - minimal data
        if ($request->routeIs('projects.index') || $request->routeIs('projects.trash')) {
            return [
                'id' => $this->id,
                'ulid' => $this->ulid,
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'status' => $this->status,
                'visibility' => $this->visibility,
                'profile_image' => $this->getMediaUrls('profile_image'),
                'members_count' => $this->whenLoaded('members', fn () => $this->members->count()),
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
            'username' => $this->username,
            'bio' => $this->bio,
            'email' => $this->email,
            'phone' => $this->phone,
            'settings' => $this->settings,
            'more_details' => $this->more_details,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'profile_image' => $this->when(
                $this->hasMedia('profile_image'),
                $this->getMediaUrls('profile_image')
            ),
            'cover_image' => $this->when(
                $this->hasMedia('cover_image'),
                $this->getMediaUrls('cover_image')
            ),
            'members' => $this->whenLoaded('members', fn () => UserMinimalResource::collection($this->members)),
            'links' => $this->whenLoaded('links'),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    /**
     * Get all media URLs including conversions for a collection
     */
    private function getMediaUrls(string $collection): array
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return [
                'original' => null,
                'lqip' => null,
                'sm' => null,
                'md' => null,
                'lg' => null,
                'xl' => null,
            ];
        }

        return [
            'original' => $media->getUrl(),
            'lqip' => $media->getUrl('lqip'),
            'sm' => $media->getUrl('sm'),
            'md' => $media->getUrl('md'),
            'lg' => $media->getUrl('lg'),
            'xl' => $media->getUrl('xl'),
        ];
    }
}
