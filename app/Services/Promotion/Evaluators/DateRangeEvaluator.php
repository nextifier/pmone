<?php

namespace App\Services\Promotion\Evaluators;

use App\Contracts\Pricing\PenaltyEvaluator;
use App\Contracts\Pricing\Purchasable;
use App\Models\PromotionRule;
use Carbon\CarbonImmutable;

/**
 * Trigger fires when now() is between fixed start/end dates.
 *
 * trigger_config: { "start": "YYYY-MM-DD" | "YYYY-MM-DD HH:MM:SS", "end": "..." }
 */
class DateRangeEvaluator implements PenaltyEvaluator
{
    public function shouldApply(PromotionRule $rule, Purchasable $entity): bool
    {
        $start = $rule->trigger_config['start'] ?? null;
        $end = $rule->trigger_config['end'] ?? null;

        if (! $start && ! $end) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($start) {
            try {
                $startCarbon = CarbonImmutable::parse($start);
                if ($now->lessThan($startCarbon)) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        if ($end) {
            try {
                $endCarbon = CarbonImmutable::parse($end);
                if ($now->greaterThan($endCarbon)) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }
}
