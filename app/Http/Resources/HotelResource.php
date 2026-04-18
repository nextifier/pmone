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
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'commission_rate' => (float) $this->commission_rate,
            'tax_percentage' => (float) $this->tax_percentage,
            'service_charge_percentage' => (float) $this->service_charge_percentage,
            'is_active' => $this->is_active,
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
