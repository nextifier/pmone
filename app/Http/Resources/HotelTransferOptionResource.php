<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelTransferOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'hotel_id' => $this->hotel_id,
            'label' => $this->label,
            'direction' => $this->direction?->value,
            'direction_label' => $this->direction?->label(),
            'vehicle_type' => $this->vehicle_type,
            'max_pax' => (int) $this->max_pax,
            'price' => (float) $this->price,
            'is_active' => $this->is_active,
            'can_edit' => auth()->user()?->can('hotels.update'),
            'can_delete' => auth()->user()?->can('hotels.update'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
