<?php

namespace App\Http\Resources;

use App\Enums\PricingType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $taxPercentage = (float) ($this->hotel?->tax_percentage ?? 0);
        $servicePercentage = (float) ($this->hotel?->service_charge_percentage ?? 0);

        $pricingType = $this->pricing_type instanceof PricingType
            ? $this->pricing_type->value
            : (string) ($this->pricing_type ?? 'flat');

        $periods = $this->whenLoaded('pricingPeriods', fn () => $this->pricingPeriods
            ->where('is_active', true)
            ->map(fn ($p) => [
                'start_date' => $p->start_date?->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                'rate' => (float) $p->rate,
                'label' => $p->label,
            ])->values()->all(), []);

        $minPeriodRate = is_array($periods) && count($periods) > 0
            ? min(array_column($periods, 'rate'))
            : null;

        $base = $pricingType === 'dynamic' && $minPeriodRate !== null
            ? (float) $minPeriodRate
            : (float) $this->base_rate;

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
            'pricing_type' => $pricingType,
            'pricing_periods' => $periods,
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
