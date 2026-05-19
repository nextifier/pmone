<?php

namespace App\Http\Resources;

use App\Services\Xendit\XenditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'reservation_number' => $this->reservation_number,
            'event_id' => $this->event_id,
            'hotel_id' => $this->hotel_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),
            'payment_expires_at' => $this->payment_expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voucher_sent_at' => $this->voucher_sent_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'guest' => [
                'name' => $this->guest_name,
                'email' => $this->guest_email,
                'phone' => $this->guest_phone,
                'identity_type' => $this->guest_identity_type?->value,
                'identity_type_label' => $this->guest_identity_type?->label(),
                'identity_number' => $this->guest_identity_number,
                'nationality' => $this->guest_nationality,
                'company' => $this->guest_company,
            ],
            'special_request' => $this->special_request,
            'amounts' => [
                'subtotal_rooms' => (float) $this->subtotal_rooms,
                'subtotal_transfer' => (float) $this->subtotal_transfer,
                'surcharge' => (float) $this->surcharge_amount,
                'penalty' => (float) $this->penalty_amount,
                'discount' => (float) $this->discount_amount,
                'tax' => (float) $this->tax_amount,
                'service' => (float) $this->service_charge_amount,
                'total' => (float) $this->total_amount,
                'refund' => $this->refund_amount !== null ? (float) $this->refund_amount : null,
            ],
            'promo_code_applied' => $this->promo_code_applied,
            'adjustments' => AppliedAdjustmentResource::collection($this->whenLoaded('adjustments')),
            'payment' => [
                'xendit_invoice_id' => $this->xendit_invoice_id,
                'xendit_payment_id' => $this->xendit_payment_id,
                'payment_url' => $this->payment_url,
                'method' => $this->payment_method?->value,
                'method_label' => $this->payment_method?->label(),
                'channel' => $this->payment_channel,
                'channel_supports_refund' => XenditService::channelSupportsRefund($this->payment_channel),
                'destination' => $this->payment_destination,
                'gateway_id' => $this->payment_gateway_id,
                'gateway' => $this->whenLoaded('paymentGateway', fn () => [
                    'id' => $this->paymentGateway->id,
                    'provider' => $this->paymentGateway->provider,
                    'label' => $this->paymentGateway->label,
                    'mode' => $this->paymentGateway->mode,
                ]),
            ],
            'refund' => [
                'amount' => $this->refund_amount !== null ? (float) $this->refund_amount : null,
                'xendit_refund_id' => $this->xendit_refund_id,
                'reason' => $this->refund_reason,
                // Manual refund is pending whenever the reservation is cancelled
                // with a refund amount that hasn't actually been disbursed yet
                // (no Xendit refund created and no `refunded_at` timestamp).
                'manual_refund_pending' => $this->status?->value === 'cancelled'
                    && $this->refund_amount !== null
                    && (float) $this->refund_amount > 0
                    && $this->xendit_refund_id === null
                    && $this->refunded_at === null,
            ],
            'cancellation_reason' => $this->cancellation_reason,
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'notes' => $this->notes,
            'voucher' => $this->when(
                $this->hasMedia('voucher'),
                fn () => [
                    'id' => $this->getFirstMedia('voucher')->id,
                    'name' => $this->getFirstMedia('voucher')->name,
                    'file_name' => $this->getFirstMedia('voucher')->file_name,
                    'mime_type' => $this->getFirstMedia('voucher')->mime_type,
                    'size' => $this->getFirstMedia('voucher')->size,
                    'url' => $this->getFirstMedia('voucher')->getUrl(),
                ]
            ),
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'slug' => $this->event->slug,
            ]),
            'hotel' => $this->whenLoaded('hotel', fn () => [
                'id' => $this->hotel->id,
                'name' => $this->hotel->name,
                'slug' => $this->hotel->slug,
                'contact_email' => $this->hotel->contact_email,
                'contact_phone' => $this->hotel->contact_phone,
            ]),
            'items' => ReservationItemResource::collection($this->whenLoaded('items')),
            'transfers' => ReservationTransferResource::collection($this->whenLoaded('transfers')),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'can_edit' => auth()->user()?->can('reservations.update'),
            'can_delete' => auth()->user()?->can('reservations.delete'),
            'can_cancel' => auth()->user()?->can('reservations.cancel'),
            'can_manual_refund' => auth()->user()?->can('reservations.refund'),
            'can_upload_voucher' => auth()->user()?->can('reservations.upload_voucher'),
            'can_send_voucher' => auth()->user()?->can('reservations.send_voucher'),
            'can_view_documents' => auth()->user()?->can('reservations.view_documents'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
