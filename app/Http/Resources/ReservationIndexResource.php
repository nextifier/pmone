<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'reservation_number' => $this->reservation_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
            'total_amount' => (float) $this->total_amount,
            'payment_method' => $this->payment_method?->value,
            'payment_channel' => $this->payment_channel,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voucher_sent_at' => $this->voucher_sent_at?->toIso8601String(),
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'has_voucher' => $this->hasMedia('voucher'),
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event?->id,
                'title' => $this->event?->title,
                'slug' => $this->event?->slug,
            ]),
            'hotel' => $this->whenLoaded('hotel', fn () => [
                'id' => $this->hotel->id,
                'name' => $this->hotel->name,
                'slug' => $this->hotel->slug,
            ]),
            'check_in_date' => $this->whenLoaded('items', fn () => optional($this->items->first())->check_in_date?->toDateString()),
            'check_out_date' => $this->whenLoaded('items', fn () => optional($this->items->last())->check_out_date?->toDateString()),
            'can_view' => auth()->user()?->can('reservations.read'),
            'can_delete' => auth()->user()?->can('reservations.delete'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
