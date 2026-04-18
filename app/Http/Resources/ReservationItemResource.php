<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'reservation_id' => $this->reservation_id,
            'room_type_id' => $this->room_type_id,
            'allotment_id' => $this->allotment_id,
            'check_in_date' => $this->check_in_date?->toDateString(),
            'check_out_date' => $this->check_out_date?->toDateString(),
            'nights' => (int) $this->nights,
            'qty' => (int) $this->qty,
            'guest_name' => $this->guest_name,
            'guest_identity' => $this->guest_identity,
            'rate_per_night' => (float) $this->rate_per_night,
            'subtotal' => (float) $this->subtotal,
            'room_type' => $this->whenLoaded('roomType', fn () => [
                'id' => $this->roomType->id,
                'name' => $this->roomType->name,
                'bed_type' => $this->roomType->bed_type,
                'max_pax' => $this->roomType->max_pax,
            ]),
        ];
    }
}
