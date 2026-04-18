<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'reservation_id' => $this->reservation_id,
            'transfer_option_id' => $this->transfer_option_id,
            'direction' => $this->direction?->value,
            'direction_label' => $this->direction?->label(),
            'transfer_date' => $this->transfer_date?->toDateString(),
            'transfer_time' => $this->transfer_time,
            'pickup_location' => $this->pickup_location,
            'dropoff_location' => $this->dropoff_location,
            'flight_number' => $this->flight_number,
            'flight_time' => $this->flight_time,
            'pax_count' => (int) $this->pax_count,
            'luggage_count' => $this->luggage_count !== null ? (int) $this->luggage_count : null,
            'note' => $this->note,
            'price' => (float) $this->price,
            'transfer_option' => $this->whenLoaded('transferOption', fn () => [
                'id' => $this->transferOption->id,
                'label' => $this->transferOption->label,
                'vehicle_type' => $this->transferOption->vehicle_type,
            ]),
        ];
    }
}
