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
            'applied_penalty_rate' => $this->applied_penalty_rate,
            'notes' => $this->notes,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
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
            ])),
            'brand_event' => $this->whenLoaded('brandEvent', fn () => [
                'id' => $this->brandEvent->id,
                'booth_number' => $this->brandEvent->booth_number,
                'booth_type' => $this->brandEvent->booth_type?->value,
                'booth_type_label' => $this->brandEvent->booth_type?->label(),
                'brand' => $this->when($this->brandEvent->relationLoaded('brand'), fn () => [
                    'id' => $this->brandEvent->brand->id,
                    'name' => $this->brandEvent->brand->name,
                    'slug' => $this->brandEvent->brand->slug,
                    'company_name' => $this->brandEvent->brand->company_name,
                ]),
            ]),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
