<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public resource — exposes resolved (single-locale) strings for translatable fields.
 * Locale resolved via app()->getLocale() set by the controller before transforming.
 */
class GuestPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->title,
            'bio' => $this->bio,
            'organization' => $this->organization,
            'is_featured' => (bool) $this->is_featured,
            'order_column' => $this->order_column,

            'profile_image' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('profile_image'),
                fn () => $this->getMediaUrls('profile_image')
            ),

            'tags' => $this->whenLoaded('tags', fn () => $this->tags
                ->where('type', 'guest_topic')
                ->pluck('name')
                ->values()
                ->all()
            ),

            'links' => $this->whenLoaded('links', fn () => $this->links->map(fn ($link) => [
                'label' => $link->label,
                'url' => $link->url,
            ])->values()->all()),
        ];
    }
}
