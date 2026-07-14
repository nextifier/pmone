<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'kind' => $this->kind?->value,
            'kind_label' => $this->kind?->label(),
            'value_type' => $this->value_type?->value,
            'value_type_label' => $this->value_type?->label(),
            'value' => (float) $this->value,
            'value_config' => $this->value_config,
            'max_discount_amount' => $this->max_discount_amount !== null ? (float) $this->max_discount_amount : null,
            'min_purchase_amount' => $this->min_purchase_amount !== null ? (float) $this->min_purchase_amount : null,
            'currency' => $this->currency,
            'applies_before_tax' => (bool) $this->applies_before_tax,
            'stacking_mode' => $this->stacking_mode?->value,
            'stacking_mode_label' => $this->stacking_mode?->label(),
            'priority' => (int) $this->priority,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'target_types' => $this->target_types,
            'applicability' => $this->applicability,
            'trigger_type' => $this->trigger_type?->value,
            'trigger_type_label' => $this->trigger_type?->label(),
            'trigger_config' => $this->trigger_config,
            'revert_usage_on_cancel' => (bool) $this->revert_usage_on_cancel,
            'is_system_manual' => (bool) $this->is_system_manual,
            'event_id' => $this->event_id,
            'project_id' => $this->project_id,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'slug' => $this->event->slug,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'username' => $this->project->username,
                'name' => $this->project->name,
            ]),
            'codes_count' => $this->whenCounted('codes'),
            'applied_count' => $this->whenCounted('appliedAdjustments'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'can_edit' => auth()->user()?->can('promotion_rules.update'),
            'can_delete' => auth()->user()?->can('promotion_rules.delete'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
