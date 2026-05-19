<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'promo_code_id' => $this->promo_code_id,
            'applied_adjustment_id' => $this->applied_adjustment_id,
            'adjustable_type' => $this->adjustable_type,
            'adjustable_id' => $this->adjustable_id,
            'email' => $this->email,
            'user_id' => $this->user_id,
            'amount_discounted' => (float) $this->amount_discounted,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
