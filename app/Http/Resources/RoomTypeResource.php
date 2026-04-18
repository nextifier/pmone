<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'hotel_id' => $this->hotel_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'max_pax' => (int) $this->max_pax,
            'bed_type' => $this->bed_type,
            'area_sqm' => $this->area_sqm !== null ? (float) $this->area_sqm : null,
            'base_rate' => (float) $this->base_rate,
            'breakfast_included' => $this->breakfast_included,
            'amenities' => $this->amenities ?? [],
            'is_active' => $this->is_active,
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
            'hotel' => $this->whenLoaded('hotel', fn () => [
                'id' => $this->hotel->id,
                'name' => $this->hotel->name,
                'slug' => $this->hotel->slug,
            ]),
            'can_edit' => auth()->user()?->can('room_types.update'),
            'can_delete' => auth()->user()?->can('room_types.delete'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
