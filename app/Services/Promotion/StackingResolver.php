<?php

namespace App\Services\Promotion;

use App\Enums\StackingMode;
use App\Models\AppliedAdjustment;
use App\Models\PromotionRule;
use Illuminate\Support\Collection;

/**
 * Determines whether a candidate rule can be applied given existing active adjustments.
 *
 * Stacking modes:
 *  - exclusive:                cannot combine with any other rule
 *  - combinable_with_promo:    can combine with other promo-code-driven rules only
 *  - combinable_with_manual:   can combine with admin manual rules only
 *  - combinable_with_all:      can combine with anything
 */
class StackingResolver
{
    /**
     * @param  Collection<int, AppliedAdjustment>  $existingAdjustments
     */
    public function canStack(PromotionRule $candidate, Collection $existingAdjustments): bool
    {
        $existing = $existingAdjustments->filter(fn (AppliedAdjustment $a) => $a->voided_at === null);

        if ($existing->isEmpty()) {
            return true;
        }

        if ($candidate->stacking_mode === StackingMode::Exclusive) {
            return false;
        }

        foreach ($existing as $existingAdj) {
            $existingRule = $existingAdj->promotionRule;

            if (! $existingRule) {
                continue;
            }

            // Bidirectional check: BOTH rules must allow the other.
            // CombinableWithAll on the candidate side does NOT bypass the existing rule's
            // restrictions — e.g. an existing CombinableWithManual rule still rejects a
            // promo-driven CombinableWithAll candidate.
            if (! $this->ruleAllowsOther($existingRule, $candidate)) {
                return false;
            }

            if (! $this->ruleAllowsOther($candidate, $existingRule)) {
                return false;
            }
        }

        return true;
    }

    private function ruleAllowsOther(PromotionRule $rule, PromotionRule $other): bool
    {
        $mode = $rule->stacking_mode;
        $otherIsManual = (bool) $other->is_system_manual;
        // Short-circuit when other is manual — codes() lookup would always be
        // overridden by the manual flag below, so skip the query.
        $otherIsPromo = ! $otherIsManual && $other->codes()->exists();

        return match ($mode) {
            StackingMode::Exclusive => false,
            StackingMode::CombinableWithAll => true,
            StackingMode::CombinableWithPromo => $otherIsPromo || $rule->id === $other->id,
            StackingMode::CombinableWithManual => $otherIsManual || $rule->id === $other->id,
        };
    }
}
