<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'code' => $this->code,
            'promotion_rule_id' => $this->promotion_rule_id,
            'usage_limit' => $this->usage_limit !== null ? (int) $this->usage_limit : null,
            'usage_count' => (int) $this->usage_count,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'issued_to_email' => $this->issued_to_email,
            'event_id' => $this->event_id,
            'is_fully_used' => $this->isFullyUsed(),
            'promotion_rule' => $this->whenLoaded('promotionRule', fn () => [
                'id' => $this->promotionRule->id,
                'ulid' => $this->promotionRule->ulid,
                'name' => $this->promotionRule->name,
                'kind' => $this->promotionRule->kind?->value,
                'value_type' => $this->promotionRule->value_type?->value,
                'value' => (float) $this->promotionRule->value,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
