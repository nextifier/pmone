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
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
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
