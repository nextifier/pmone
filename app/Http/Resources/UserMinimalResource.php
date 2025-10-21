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
            'profile_image' => $this->when(
                $this->hasMedia('profile_image'),
                $this->getMediaUrls('profile_image')
            ),
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
