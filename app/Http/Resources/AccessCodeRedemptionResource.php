<?php

namespace App\Http\Resources;

use App\Models\AccessCodeRedemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessCodeRedemption
 */
class AccessCodeRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'email' => $this->email,
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'status' => $this->voided_at ? 'released' : ($this->redeemed_at ? 'confirmed' : 'held'),
            'order' => $this->whenLoaded('ticketOrder', fn () => [
                'ulid' => $this->ticketOrder?->ulid,
                'order_number' => $this->ticketOrder?->order_number,
                'status' => $this->ticketOrder?->status?->value,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
