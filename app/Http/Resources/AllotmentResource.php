<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllotmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'hotel_id' => $this->hotel_id,
            'room_type_id' => $this->room_type_id,
            'quantity' => (int) $this->quantity,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'release_at' => $this->release_at?->toIso8601String(),
            'surcharge_type' => $this->surcharge_type,
            'surcharge_amount' => $this->surcharge_amount !== null ? (float) $this->surcharge_amount : null,
            'is_active' => $this->is_active,
            'room_type' => $this->whenLoaded('roomType', fn () => [
                'id' => $this->roomType->id,
                'name' => $this->roomType->name,
                'base_rate' => (float) $this->roomType->base_rate,
            ]),
            'hotel' => $this->whenLoaded('hotel', fn () => [
                'id' => $this->hotel->id,
                'name' => $this->hotel->name,
                'slug' => $this->hotel->slug,
            ]),
            'can_edit' => auth()->user()?->can('allotments.update'),
            'can_delete' => auth()->user()?->can('allotments.delete'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
