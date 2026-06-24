<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Staff-only fields (internal notes, invoice/receipt) must never leak to
        // exhibitors, who consume this same resource via the dashboard.
        $isStaff = $request->user()?->hasRole(['master', 'admin', 'staff']) ?? false;

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'brand_event_id' => $this->brand_event_id,
            'order_number' => $this->order_number,
            'operational_status' => $this->operational_status?->value,
            'operational_status_label' => $this->operational_status?->label(),
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'cancellation_reason' => $this->cancellation_reason,
            'order_period' => $this->order_period,
            'notes' => $this->notes,
            'internal_notes' => $this->when($isStaff, fn () => $this->internal_notes),
            'invoice' => $this->when($isStaff, fn () => $this->hasMedia('invoice') ? [
                'name' => $this->getFirstMedia('invoice')?->name,
                'url' => $this->getFirstMediaUrl('invoice'),
            ] : null),
            'receipt' => $this->when($isStaff, fn () => $this->hasMedia('receipt') ? [
                'name' => $this->getFirstMedia('receipt')?->name,
                'url' => $this->getFirstMediaUrl('receipt'),
            ] : null),
            'subtotal' => $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'penalty_amount' => (float) $this->penalty_amount,
            'promo_code_applied' => $this->promo_code_applied,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'adjustments' => AppliedAdjustmentResource::collection($this->whenLoaded('adjustments')),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'event_product_id' => $item->event_product_id,
                'product_name' => $item->product_name,
                'product_category' => $item->productCategory?->title,
                'product_image_url' => $item->product_image_url,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'total_price' => $item->total_price,
                'notes' => $item->notes,
                'internal_notes' => $isStaff ? $item->internal_notes : null,
            ])),
            'brand_event' => $this->whenLoaded('brandEvent', fn () => [
                'id' => $this->brandEvent->id,
                'booth_number' => $this->brandEvent->booth_number,
                'booth_type' => $this->brandEvent->booth_type?->value,
                'booth_type_label' => $this->brandEvent->booth_type?->label(),
                'brand' => $this->when(
                    $this->brandEvent->relationLoaded('brand') && $this->brandEvent->brand !== null,
                    fn () => [
                        'id' => $this->brandEvent->brand->id,
                        'name' => $this->brandEvent->brand->name,
                        'slug' => $this->brandEvent->brand->slug,
                        'company_name' => $this->brandEvent->brand->company_name,
                        'brand_logo' => $this->brandEvent->brand->relationLoaded('media')
                            ? $this->brandEvent->brand->brand_logo
                            : null,
                    ]
                ),
            ]),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
