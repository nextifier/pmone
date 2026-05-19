<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'slug' => $this->slug,
            'name' => $this->name,
            'city' => $this->city,
            'country' => $this->country,
            'commission_rate' => (float) $this->commission_rate,
            'is_active' => $this->is_active,
            'featured' => $this->when(
                $this->hasMedia('featured'),
                fn () => [
                    'sm' => $this->getFirstMediaUrl('featured', 'sm'),
                    'md' => $this->getFirstMediaUrl('featured', 'md'),
                ]
            ),
            'room_types_count' => $this->whenCounted('roomTypes'),
            'events_count' => $this->whenCounted('events'),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($ev) => [
                'id' => $ev->id,
                'slug' => $ev->slug,
                'title' => $ev->title,
                'project' => $ev->relationLoaded('project') && $ev->project
                    ? ['username' => $ev->project->username, 'name' => $ev->project->name]
                    : null,
                'pivot' => $ev->pivot ? [
                    'is_active' => (bool) $ev->pivot->is_active,
                ] : null,
            ])),
            'pivot' => $this->when(isset($this->pivot), fn () => [
                'id' => $this->pivot?->id,
                'is_active' => (bool) ($this->pivot?->is_active),
                'order_column' => $this->pivot?->order_column,
                'notes' => $this->pivot?->notes,
            ]),
            'can_edit' => auth()->user()?->can('hotels.update'),
            'can_delete' => auth()->user()?->can('hotels.delete'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
