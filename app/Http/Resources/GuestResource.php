<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'slug' => $this->slug,

            'title' => $this->title,
            'bio' => $this->bio,

            'organization' => $this->organization,

            'status' => $this->status,
            'visibility' => $this->visibility,
            'is_featured' => (bool) $this->is_featured,
            'order_column' => $this->order_column,

            'appearance_date' => $this->more_details['appearance_date'] ?? null,
            'transparent_background' => $this->more_details['transparent_background'] ?? false,

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
                'id' => $link->id,
                'label' => $link->label,
                'url' => $link->url,
                'order' => $link->order,
            ])->values()->all()),

            'can_edit' => $user ? $user->can('guests.update') : false,
            'can_delete' => $user ? $user->can('guests.delete') : false,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),

            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'updater' => $this->whenLoaded('updater', fn () => $this->updater ? [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
            ] : null),
            'deleter' => $this->whenLoaded('deleter', fn () => $this->deleter ? [
                'id' => $this->deleter->id,
                'name' => $this->deleter->name,
            ] : null),
        ];
    }
}
