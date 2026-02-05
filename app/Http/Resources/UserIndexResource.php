<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'title' => $this->title,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'is_online' => $this->isOnline(),
            'last_seen' => $this->last_seen?->toISOString(),
            'created_at' => $this->created_at->toISOString(),

            // Profile Image
            'profile_image' => $this->when(
                $this->hasMedia('profile_image'),
                $this->getMediaUrls('profile_image')
            ),

            // Roles - optimized to avoid duplicate queries
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),

            // Counts
            'posts_count' => $this->when(isset($this->posts_count), $this->posts_count ?? 0),

            // Tracking fields
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'deleted_at' => $this->deleted_at?->toISOString(),

            // Relationships
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'username' => $this->creator->username,
            ]),
            'updater' => $this->whenLoaded('updater', fn () => [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
                'username' => $this->updater->username,
            ]),
            'deleter' => $this->whenLoaded('deleter', fn () => [
                'id' => $this->deleter->id,
                'name' => $this->deleter->name,
                'username' => $this->deleter->username,
            ]),
        ];
    }
}
