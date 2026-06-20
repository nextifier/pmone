<?php

namespace App\Http\Resources;

use App\Models\TicketPricePhase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketPricePhase
 */
class TicketPricePhaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'label' => $this->label,
            'price' => $this->price,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'quota' => $this->quota,
            'sold_count' => $this->sold_count,
            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
