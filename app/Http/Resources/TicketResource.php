<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin detail resource — exposes all locale translations of `title` plus the
 * ticket's price phases, sessions, and valid days for the edit form.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'event_id' => $this->event_id,
            'slug' => $this->slug,
            'kind' => $this->kind?->value,
            'title' => $this->getTranslations('title'),
            'tier' => $this->tier,
            'benefits' => $this->benefits ?? [],
            'currency' => $this->currency,
            'purchase_type' => $this->purchase_type?->value,
            'external_url' => $this->external_url,
            'more_details' => $this->more_details ?? [],
            'settings' => $this->settings ?? [],
            'print_on_redeem' => (bool) $this->print_on_redeem,
            'requires_day_selection' => (bool) $this->requires_day_selection,
            'stock' => $this->stock,
            'sold_count' => $this->sold_count,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'is_active' => (bool) $this->is_active,
            'visibility' => $this->visibility?->value,
            'order_column' => $this->order_column,

            'poster' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('poster'),
                fn () => $this->getMediaUrls('poster')
            ),

            'valid_day_ids' => $this->whenLoaded('validDays', fn () => $this->validDays->pluck('id')),
            'valid_days' => EventDayResource::collection($this->whenLoaded('validDays')),
            'price_phases' => TicketPricePhaseResource::collection($this->whenLoaded('pricePhases')),
            'sessions' => TicketSessionResource::collection($this->whenLoaded('sessions')),

            'can_edit' => $user ? $user->can('tickets.update') : false,
            'can_delete' => $user ? $user->can('tickets.delete') : false,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
