<?php

namespace App\Http\Resources;

use App\Enums\Ticketing\TicketVisibility;
use App\Models\Ticket;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public ticket shape for event websites: localized title, the currently active
 * phase price (null when not on sale), benefits, poster, sessions for add-ons.
 *
 * @mixin Ticket
 */
class PublicTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(TicketPurchaseService::class);
        $phase = $this->relationLoaded('pricePhases') ? $service->resolveActivePhase($this->resource) : null;

        $upcomingPhase = null;
        if ($phase === null && $this->relationLoaded('pricePhases')) {
            $now = now();
            $upcomingPhase = $this->pricePhases
                ->where('is_active', true)
                ->filter(fn ($p) => $p->starts_at !== null && $now->lt($p->starts_at))
                ->sortBy('starts_at')
                ->first();
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'kind' => $this->kind?->value,
            'title' => $this->getTranslation('title', app()->getLocale(), false) ?: $this->getTranslation('title', 'en', false),
            'tier' => $this->tier,
            'day_pass' => $this->more_details['day_pass'] ?? null,
            'entrance' => $this->more_details['entrance'] ?? null,
            'benefits' => $this->benefits ?? [],
            'currency' => $this->currency,
            'purchase_type' => $this->purchase_type?->value,
            'external_url' => $this->external_url,
            'visibility' => $this->visibility?->value,
            'locked' => $this->visibility === TicketVisibility::CodeRequired,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'poster' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('poster'),
                fn () => $this->getMediaUrls('poster')
            ),
            'on_sale' => $phase !== null,
            'price' => $phase ? (float) $phase->price : null,
            'display_price' => $phase ? (float) $phase->price : ($upcomingPhase ? (float) $upcomingPhase->price : null),
            'phase_label' => $phase?->label,
            'sales_status' => $phase !== null ? 'on_sale' : ($upcomingPhase !== null ? 'upcoming' : 'closed'),
            'sales_starts_at' => $upcomingPhase?->starts_at,
            'sales_ends_at' => $phase?->ends_at,
            'sales_phase_label' => $phase?->label ?? $upcomingPhase?->label,
            'available' => $this->when($phase !== null, fn () => $service->availableStock($this->resource)),
            'valid_day_ids' => $this->whenLoaded('validDays', fn () => $this->validDays->pluck('id')),
            'requires_day_selection' => (bool) $this->requires_day_selection,
            'valid_days' => $this->whenLoaded('validDays', fn () => $this->validDays
                ->sortBy('day_number')
                ->values()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'day_number' => $d->day_number,
                    'label' => $d->label ?: 'Day '.$d->day_number,
                    'date' => $d->date?->toDateString(),
                ])),
            'sessions' => $this->when(
                $this->kind?->value === 'add_on' && $this->relationLoaded('sessions'),
                fn () => $this->sessions->where('is_active', true)->values()->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'starts_at' => $s->starts_at,
                    'ends_at' => $s->ends_at,
                    'location' => $s->location,
                    'host' => $s->host,
                    'available' => $service->availableSessionCapacity($s),
                ])
            ),
        ];
    }
}
