<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserMinimalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'title' => $this->title,
            'profile_image' => $this->getProfileImageFromLoadedMedia(),
            'pivot' => $this->when(
                isset($this->pivot),
                [
                    'role' => $this->pivot->role ?? null,
                    'order' => $this->pivot->order ?? 0,
                ]
            ),
        ];
    }

    /**
     * Get profile image from already loaded media relationship to avoid N+1
     */
    private function getProfileImageFromLoadedMedia(): mixed
    {
        // Check if media relationship is loaded
        if ($this->relationLoaded('media')) {
            $profileMedia = $this->media->firstWhere('collection_name', 'profile_image');

            if ($profileMedia) {
                return $this->getMediaUrls('profile_image');
            }
        }

        return null;
    }
}
