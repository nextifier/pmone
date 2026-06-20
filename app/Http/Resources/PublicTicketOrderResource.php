<?php

namespace App\Http\Resources;

use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public ticket-order shape returned after checkout and on the status page.
 *
 * @mixin TicketOrder
 */
class PublicTicketOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'is_free' => $this->isFree(),
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'promo_code_applied' => $this->promo_code_applied,
            'payment_url' => $this->payment_url,
            'payment_expires_at' => $this->payment_expires_at,
            'paid_at' => $this->paid_at,
            'magic_link' => $this->when(
                $this->magicLinkRaw !== null,
                fn () => $this->magicLinkRaw
            ),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'ticket_id' => $item->ticket_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'phase_label' => $item->phase_label,
                'subtotal' => (float) $item->subtotal,
            ])),
            'attendees' => AttendeeResource::collection($this->whenLoaded('attendees')),
            'created_at' => $this->created_at,
        ];
    }
}
