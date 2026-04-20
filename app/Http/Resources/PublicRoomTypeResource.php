<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $taxPercentage = (float) ($this->hotel?->tax_percentage ?? 0);
        $servicePercentage = (float) ($this->hotel?->service_charge_percentage ?? 0);
        $base = (float) $this->base_rate;
        $allInRate = round($base * (1 + ($taxPercentage + $servicePercentage) / 100), 2);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'max_pax' => (int) $this->max_pax,
            'bed_type' => $this->bed_type,
            'area_sqm' => $this->area_sqm !== null ? (float) $this->area_sqm : null,
            'base_rate' => $base,
            'all_in_rate' => $allInRate,
            'breakfast_included' => $this->breakfast_included,
            'smoking_allowed' => (bool) $this->smoking_allowed,
            'amenities' => $this->tags->pluck('name')->values(),
            'cancellation_policy' => $this->cancellation_policy,
            'gallery' => $this->getMedia('gallery')->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'sm' => $media->hasGeneratedConversion('sm') ? $media->getUrl('sm') : $media->getUrl(),
                'md' => $media->hasGeneratedConversion('md') ? $media->getUrl('md') : $media->getUrl(),
            ])->values(),
        ];
    }
}
