<?php

namespace App\Services\Promotion;

use App\Contracts\Pricing\Purchasable;
use App\Enums\AdjustmentKind;
use App\Enums\PenaltyTriggerType;
use App\Models\AppliedAdjustment;
use App\Models\PromotionRule;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;

/**
 * Handles penalty rule auto-evaluation, manual penalty apply, and cancellation fees.
 */
class PenaltyService
{
    public function __construct(
        private readonly PenaltyEvaluatorRegistry $registry,
        private readonly PricingService $pricing,
        private readonly ApplicabilityChecker $applicability,
    ) {}

    /**
     * Iterate auto-evaluated penalty rules and apply matching ones to the entity.
     *
     * @return array<int, AppliedAdjustment>
     */
    public function evaluateAndApply(Purchasable $entity): array
    {
        $autoTriggers = [
            PenaltyTriggerType::BookingWindow->value,
            PenaltyTriggerType::EventPeriod->value,
            PenaltyTriggerType::DateRange->value,
            PenaltyTriggerType::LeadTime->value,
        ];

        $morphClass = $entity->getMorphClass();
        $basename = class_basename($morphClass);

        $rules = PromotionRule::query()
            ->active()
            ->withinWindow()
            ->where('kind', AdjustmentKind::Penalty->value)
            ->whereIn('trigger_type', $autoTriggers)
            ->where(function ($q) use ($morphClass, $basename) {
                $q->whereNull('target_types')
                    ->orWhereJsonContains('target_types', $morphClass)
                    ->orWhereJsonContains('target_types', $basename);
            })
            ->orderBy('priority')
            ->get();

        $applied = [];

        foreach ($rules as $rule) {
            if ($this->alreadyApplied($entity, $rule)) {
                continue;
            }

            if (! $rule->appliesToType($morphClass)) {
                continue;
            }

            $applicabilityResult = $this->applicability->run(
                $rule->applicability ?? [],
                $entity,
                $entity->customerEmail() ?? '',
                null,
            );
            if (! $applicabilityResult->passes) {
                continue;
            }

            $evaluator = $this->registry->for($rule->trigger_type);

            if (! $evaluator->shouldApply($rule, $entity)) {
                continue;
            }

            $applied[] = $this->createSnapshot($rule, $entity, appliedBy: 'system');
        }

        if (! empty($applied)) {
            $this->pricing->recalculateAndPersist($entity);
        }

        return $applied;
    }

    /**
     * Manually apply a penalty (or any rule) with optional value override.
     */
    public function applyManual(
        Purchasable $entity,
        PromotionRule $rule,
        ?float $overrideValue = null,
        ?int $appliedByUserId = null,
        ?string $reason = null,
    ): AppliedAdjustment {
        return DB::transaction(function () use ($entity, $rule, $overrideValue, $appliedByUserId, $reason) {
            $adj = $this->createSnapshot(
                $rule,
                $entity,
                appliedBy: $appliedByUserId ? "admin:{$appliedByUserId}" : 'system',
                overrideValue: $overrideValue,
                reason: $reason,
            );

            $this->pricing->recalculateAndPersist($entity);

            return $adj->fresh();
        });
    }

    /**
     * Evaluate and apply cancellation_window rules during cancel flow.
     *
     * Returns null if no rule matches.
     */
    public function applyCancellationFee(Purchasable $entity): ?AppliedAdjustment
    {
        $morphClass = $entity->getMorphClass();
        $basename = class_basename($morphClass);

        $rules = PromotionRule::query()
            ->active()
            ->withinWindow()
            ->where('kind', AdjustmentKind::Penalty->value)
            ->where('trigger_type', PenaltyTriggerType::CancellationWindow->value)
            ->where(function ($q) use ($morphClass, $basename) {
                $q->whereNull('target_types')
                    ->orWhereJsonContains('target_types', $morphClass)
                    ->orWhereJsonContains('target_types', $basename);
            })
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($this->alreadyApplied($entity, $rule)) {
                continue;
            }

            $evaluator = $this->registry->for($rule->trigger_type);

            if (! $evaluator->shouldApply($rule, $entity)) {
                continue;
            }

            $adj = $this->createSnapshot($rule, $entity, appliedBy: 'system');
            $this->pricing->recalculateAndPersist($entity);

            return $adj->fresh();
        }

        return null;
    }

    private function alreadyApplied(Purchasable $entity, PromotionRule $rule): bool
    {
        return $entity->adjustments()
            ->where('promotion_rule_id', $rule->id)
            ->whereNull('voided_at')
            ->exists();
    }

    private function createSnapshot(
        PromotionRule $rule,
        Purchasable $entity,
        string $appliedBy,
        ?float $overrideValue = null,
        ?string $reason = null,
    ): AppliedAdjustment {
        $value = $overrideValue !== null ? $overrideValue : (float) $rule->value;

        return AppliedAdjustment::query()->create([
            'adjustable_type' => $entity->getMorphClass(),
            'adjustable_id' => $entity->getKey(),
            'promotion_rule_id' => $rule->id,
            'promo_code_id' => null,
            'kind' => $rule->kind->value,
            'label' => $reason ? "{$rule->name} - {$reason}" : $rule->name,
            'value_type' => $rule->value_type->value,
            'value' => $value,
            'value_config' => $rule->value_config,
            'base_amount' => 0, // recalculate fills
            'amount' => 0,
            'rule_snapshot' => $this->buildRuleSnapshot($rule, $overrideValue, $reason),
            'applied_by' => $appliedBy,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRuleSnapshot(PromotionRule $rule, ?float $overrideValue, ?string $reason): array
    {
        return [
            'rule_id' => $rule->id,
            'rule_ulid' => $rule->ulid,
            'rule_name' => $rule->name,
            'rule_slug' => $rule->slug,
            'kind' => $rule->kind?->value,
            'value_type' => $rule->value_type?->value,
            'value' => $overrideValue !== null ? $overrideValue : (float) $rule->value,
            'override_value' => $overrideValue,
            'reason' => $reason,
            'applies_before_tax' => (bool) $rule->applies_before_tax,
            'stacking_mode' => $rule->stacking_mode?->value,
            'priority' => $rule->priority,
            'trigger_type' => $rule->trigger_type?->value,
            'trigger_config' => $rule->trigger_config,
            'is_system_manual' => (bool) $rule->is_system_manual,
            'snapshot_at' => now()->toIso8601String(),
        ];
    }
}
