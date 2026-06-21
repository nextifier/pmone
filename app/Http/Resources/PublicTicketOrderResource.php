<?php

namespace App\Http\Resources;

use App\Enums\Ticketing\TicketOrderStatus;
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
            // The qr_token is the gate-scanner access key, so it is only revealed
            // once the order is Confirmed (free/comp/paid). For a pending order the
            // attendee rows still show (names, ticket) but the token stays null, so
            // unpaid tickets can never be scanned or downloaded as a usable QR.
            'attendees' => $this->whenLoaded('attendees', fn () => $this->attendees->map(function ($attendee) {
                $row = (new AttendeeResource($attendee))->resolve();
                if ($this->status !== TicketOrderStatus::Confirmed) {
                    $row['qr_token'] = null;
                }

                return $row;
            })),
            'created_at' => $this->created_at,
        ];
    }
}
