<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'code' => $this->code,
            'promotion_rule_id' => $this->promotion_rule_id,
            'usage_limit' => $this->usage_limit !== null ? (int) $this->usage_limit : null,
            'usage_limit_per_email' => $this->usage_limit_per_email !== null ? (int) $this->usage_limit_per_email : null,
            'usage_count' => (int) $this->usage_count,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'issued_to_email' => $this->issued_to_email,
            'metadata' => $this->metadata,
            'event_id' => $this->event_id,
            'is_fully_used' => $this->isFullyUsed(),
            'promotion_rule' => $this->whenLoaded('promotionRule', fn () => (new PromotionRuleIndexResource($this->promotionRule))->resolve()),
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'slug' => $this->event->slug,
            ]),
            'usages_count' => $this->whenCounted('usages'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'can_edit' => auth()->user()?->can('promo_codes.update'),
            'can_delete' => auth()->user()?->can('promo_codes.delete'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
