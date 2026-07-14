<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionRuleIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'kind' => $this->kind?->value,
            'kind_label' => $this->kind?->label(),
            'value_type' => $this->value_type?->value,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'stacking_mode' => $this->stacking_mode?->value,
            'stacking_mode_label' => $this->stacking_mode?->label(),
            'priority' => (int) $this->priority,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'is_system_manual' => (bool) $this->is_system_manual,
            'trigger_type' => $this->trigger_type?->value,
            'event_id' => $this->event_id,
            'codes_count' => $this->whenCounted('codes'),
            'applied_count' => $this->whenCounted('appliedAdjustments'),
            'can_edit' => auth()->user()?->can('promotion_rules.update'),
            'can_delete' => auth()->user()?->can('promotion_rules.delete'),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
