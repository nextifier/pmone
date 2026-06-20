<?php

namespace App\Http\Resources;

use App\Models\TicketSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketSession
 */
class TicketSessionResource extends JsonResource
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
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'location' => $this->location,
            'host' => $this->host,
            'capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
