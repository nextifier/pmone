<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderIndexResource extends JsonResource
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
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'items_count' => $this->when($this->items_count !== null, $this->items_count),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
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
                'event' => $this->when($this->brandEvent->relationLoaded('event'), fn () => [
                    'id' => $this->brandEvent->event->id,
                    'title' => $this->brandEvent->event->title,
                    'slug' => $this->brandEvent->event->slug,
                    'project' => $this->when(
                        $this->brandEvent->event->relationLoaded('project') && $this->brandEvent->event->project,
                        fn () => [
                            'username' => $this->brandEvent->event->project->username,
                        ]
                    ),
                ]),
            ]),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'created_at' => $this->created_at,
        ];
    }
}
