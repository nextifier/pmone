<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for the admin tickets list table.
 *
 * @mixin Ticket
 */
class TicketIndexResource extends JsonResource
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
            'title' => $this->getTranslation('title', app()->getLocale(), false),
            'tier' => $this->tier,
            'currency' => $this->currency,
            'purchase_type' => $this->purchase_type?->value,
            'stock' => $this->stock,
            'sold_count' => $this->sold_count,
            'is_active' => (bool) $this->is_active,
            'visibility' => $this->visibility?->value,
            'order_column' => $this->order_column,
            'price_phases_count' => $this->whenCounted('pricePhases'),
            'sessions_count' => $this->whenCounted('sessions'),
            'poster' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('poster'),
                fn () => $this->getMediaUrls('poster')
            ),
            'can_edit' => $user ? $user->can('tickets.update') : false,
            'can_delete' => $user ? $user->can('tickets.delete') : false,
            'created_at' => $this->created_at,
        ];
    }
}
