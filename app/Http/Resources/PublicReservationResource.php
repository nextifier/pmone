<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reservation_number' => $this->reservation_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'payment_expires_at' => $this->payment_expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voucher_sent_at' => $this->voucher_sent_at?->toIso8601String(),
            'guest' => [
                'name' => $this->guest_name,
                'email' => $this->guest_email,
                'phone' => $this->guest_phone,
            ],
            'amounts' => [
                'subtotal_rooms' => (float) $this->subtotal_rooms,
                'subtotal_transfer' => (float) $this->subtotal_transfer,
                'surcharge' => (float) $this->surcharge_amount,
                'penalty' => (float) $this->penalty_amount,
                'discount' => (float) $this->discount_amount,
                'tax' => (float) $this->tax_amount,
                'service' => (float) $this->service_charge_amount,
                'total' => (float) $this->total_amount,
            ],
            'promo_code_applied' => $this->promo_code_applied,
            'payment_url' => $this->payment_url,
            'hotel' => $this->whenLoaded('hotel', fn () => [
                'name' => $this->hotel->name,
                'slug' => $this->hotel->slug,
                'address' => $this->hotel->street,
                'contact_email' => $this->hotel->contact_email,
                'contact_phone' => $this->hotel->contact_phone,
                'cancellation_policy' => $this->hotel->cancellation_policy,
            ]),
            'special_request' => $this->special_request,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'room_type_name' => $item->roomType?->name,
                'check_in_date' => $item->check_in_date?->toDateString(),
                'check_out_date' => $item->check_out_date?->toDateString(),
                'nights' => $item->nights,
                'qty' => $item->qty,
                'rate_per_night' => (float) $item->rate_per_night,
                'subtotal' => (float) $item->subtotal,
                'notes' => $item->notes,
            ])),
            'transfers' => $this->whenLoaded('transfers', fn () => $this->transfers->map(fn ($t) => [
                'direction' => $t->direction?->value,
                'direction_label' => $t->direction?->label(),
                'transfer_date' => $t->transfer_date?->toDateString(),
                'pickup_location' => $t->pickup_location,
                'dropoff_location' => $t->dropoff_location,
                'pax_count' => $t->pax_count,
                'price' => (float) $t->price,
                'note' => $t->note,
            ])),
        ];
    }
}
