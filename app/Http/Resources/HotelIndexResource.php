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
            'province' => $this->province,
            'country' => $this->country,
            'commission_rate' => (float) $this->commission_rate,
            'star_rating' => $this->star_rating !== null ? (int) $this->star_rating : null,
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

            // Per-event aggregations populated by HotelController::index when
            // the hotel list is scoped to a single event. Null when fetched
            // from a context that didn't add the subqueries.
            'allotment_total' => $this->when(
                $this->resource->allotment_total !== null,
                fn () => (int) $this->resource->allotment_total,
            ),
            'allotment_sold' => $this->when(
                $this->resource->allotment_sold !== null,
                fn () => (int) $this->resource->allotment_sold,
            ),
            'paid_reservations_count' => $this->when(
                $this->resource->paid_reservations_count !== null,
                fn () => (int) $this->resource->paid_reservations_count,
            ),
            'revenue' => $this->when(
                $this->resource->revenue !== null,
                fn () => (float) $this->resource->revenue,
            ),
            'last_booking_at' => $this->when(
                $this->resource->last_booking_at !== null,
                fn () => $this->resource->last_booking_at,
            ),
            'price_min' => $this->when(
                $this->resource->price_min !== null,
                fn () => (float) $this->resource->price_min,
            ),
            'price_max' => $this->when(
                $this->resource->price_max !== null,
                fn () => (float) $this->resource->price_max,
            ),
            'has_dynamic_pricing' => $this->when(
                $this->resource->has_dynamic_pricing !== null,
                fn () => (bool) $this->resource->has_dynamic_pricing,
            ),
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
