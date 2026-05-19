<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicHotelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'event' => $this->whenLoaded('events', function () {
                // Controller filters `events` to the relevant event (single match).
                $event = $this->events->first();

                return $event ? [
                    'id' => $event->id,
                    'slug' => $event->slug,
                    'title' => $event->title,
                    'start_date' => $event->start_date?->toIso8601String(),
                    'end_date' => $event->end_date?->toIso8601String(),
                    'is_active' => (bool) $event->is_active,
                    'project' => $event->relationLoaded('project') ? [
                        'id' => $event->project?->id,
                        'username' => $event->project?->username,
                        'name' => $event->project?->name,
                    ] : null,
                ] : null;
            }),
            'name' => $this->name,
            'description' => $this->description,
            'star_rating' => $this->star_rating,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'google_maps_link' => $this->google_maps_link,
            'google_maps_embed_src' => $this->google_maps_embed_src,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'facilities' => $this->tags->pluck('name')->values(),
            'cancellation_policy' => $this->cancellation_policy,
            'tax_percentage' => (float) $this->tax_percentage,
            'service_charge_percentage' => (float) $this->service_charge_percentage,
            'featured' => $this->when(
                $this->hasMedia('featured'),
                fn () => $this->getMediaUrls('featured')
            ),
            'gallery' => $this->getMedia('gallery')->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'sm' => $media->hasGeneratedConversion('sm') ? $media->getUrl('sm') : $media->getUrl(),
                'md' => $media->hasGeneratedConversion('md') ? $media->getUrl('md') : $media->getUrl(),
                'lg' => $media->hasGeneratedConversion('lg') ? $media->getUrl('lg') : $media->getUrl(),
            ])->values(),
            'room_types' => PublicRoomTypeResource::collection($this->whenLoaded('roomTypes')),
            'transfer_options' => $this->whenLoaded('transferOptions', fn () => $this->transferOptions->map(fn ($opt) => [
                'id' => $opt->id,
                'label' => $opt->label,
                'direction' => $opt->direction?->value,
                'direction_label' => $opt->direction?->label(),
                'vehicle_type' => $opt->vehicle_type,
                'max_pax' => $opt->max_pax,
                'price' => (float) $opt->price,
            ])),
        ];
    }
}
