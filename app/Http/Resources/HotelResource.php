<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'star_rating' => $this->star_rating,
            'address' => $this->street,
            'city' => $this->city,
            'province' => $this->province,
            'country' => $this->country,
            'google_maps_link' => $this->google_maps_link,
            'google_maps_embed_src' => $this->google_maps_embed_src,
            'facilities' => $this->tags->pluck('name')->values(),
            'cancellation_policy' => $this->cancellation_policy,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'commission_rate' => (float) $this->commission_rate,
            'tax_percentage' => (float) $this->tax_percentage,
            'service_charge_percentage' => (float) $this->service_charge_percentage,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'more_details' => $this->more_details,
            'order_column' => $this->order_column,
            'featured' => $this->when(
                $this->hasMedia('featured'),
                fn () => $this->getMediaUrls('featured')
            ),
            'gallery' => $this->getMedia('gallery')->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'order_column' => $media->order_column,
                'url' => $media->getUrl(),
                'lqip' => $media->hasGeneratedConversion('lqip') ? $media->getUrl('lqip') : null,
                'sm' => $media->hasGeneratedConversion('sm') ? $media->getUrl('sm') : null,
                'md' => $media->hasGeneratedConversion('md') ? $media->getUrl('md') : null,
                'lg' => $media->hasGeneratedConversion('lg') ? $media->getUrl('lg') : null,
                'xl' => $media->hasGeneratedConversion('xl') ? $media->getUrl('xl') : null,
            ])->values(),
            'room_types_count' => $this->whenCounted('roomTypes'),
            'reservations_count' => $this->whenCounted('reservations'),
            'events_count' => $this->whenCounted('events'),
            'room_types' => $this->whenLoaded('roomTypes', fn () => $this->roomTypes->map(fn ($r) => [
                'id' => $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
                'description' => $r->description,
                'max_pax' => $r->max_pax,
                'bed_type' => $r->bed_type,
                'area_sqm' => $r->area_sqm !== null ? (float) $r->area_sqm : null,
                'base_rate' => (float) $r->base_rate,
                'breakfast_included' => (bool) $r->breakfast_included,
                'is_active' => (bool) $r->is_active,
                'deleted_at' => $r->deleted_at,
                'gallery' => $r->getMedia('gallery')->map(fn ($media) => [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'sm' => $media->hasGeneratedConversion('sm') ? $media->getUrl('sm') : null,
                    'md' => $media->hasGeneratedConversion('md') ? $media->getUrl('md') : null,
                    'lg' => $media->hasGeneratedConversion('lg') ? $media->getUrl('lg') : null,
                ])->values(),
            ])->values()),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'id' => $e->id,
                'slug' => $e->slug,
                'title' => $e->title,
                'is_active' => (bool) $e->is_active,
                'project' => $e->relationLoaded('project') && $e->project
                    ? ['username' => $e->project->username, 'name' => $e->project->name]
                    : null,
                'pivot' => [
                    'id' => $e->pivot->id,
                    'is_active' => (bool) $e->pivot->is_active,
                    'notes' => $e->pivot->notes,
                    'order_column' => $e->pivot->order_column,
                    'attached_at' => $e->pivot->created_at?->toIso8601String(),
                ],
            ])->values()),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'can_edit' => auth()->user()?->can('hotels.update'),
            'can_delete' => auth()->user()?->can('hotels.delete'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
