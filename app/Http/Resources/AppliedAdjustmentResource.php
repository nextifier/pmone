<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppliedAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'order_item_id' => $this->order_item_id,
            'kind' => $this->kind?->value,
            'kind_label' => $this->kind?->label(),
            'kind_color' => $this->kind?->color(),
            'label' => $this->label,
            'value_type' => $this->value_type?->value,
            'value_type_label' => $this->value_type?->label(),
            'value' => (float) $this->value,
            'value_config' => $this->value_config,
            'base_amount' => (float) $this->base_amount,
            'amount' => (float) $this->amount,
            'line_breakdown' => $this->line_breakdown,
            'promotion_rule_id' => $this->promotion_rule_id,
            'promo_code_id' => $this->promo_code_id,
            'applied_by' => $this->applied_by,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'is_voided' => $this->isVoided(),
            'promotion_rule' => $this->whenLoaded('promotionRule', fn () => [
                'id' => $this->promotionRule->id,
                'ulid' => $this->promotionRule->ulid,
                'name' => $this->promotionRule->name,
                'is_system_manual' => (bool) $this->promotionRule->is_system_manual,
            ]),
            'promo_code' => $this->whenLoaded('promoCode', fn () => $this->promoCode ? [
                'id' => $this->promoCode->id,
                'code' => $this->promoCode->code,
            ] : null),
            'can_void' => auth()->user()?->can('promotions.void_adjustment'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
