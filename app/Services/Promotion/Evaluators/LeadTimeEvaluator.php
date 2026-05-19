<?php

namespace App\Services\Promotion\Evaluators;

use App\Contracts\Pricing\PenaltyEvaluator;
use App\Contracts\Pricing\Purchasable;
use App\Models\PromotionRule;
use Carbon\CarbonImmutable;

/**
 * Late booking surcharge: fires when nearest check-in is within (or beyond) max_days.
 *
 * trigger_config: { "max_days": int, "operator": "lt" | "lte" | "gt" | "gte" }
 *
 * Expects entity context to provide either:
 *  - `nearest_check_in` (Carbon|string)
 *  - or `items` collection with `check_in_date` field per item
 */
class LeadTimeEvaluator implements PenaltyEvaluator
{
    public function shouldApply(PromotionRule $rule, Purchasable $entity): bool
    {
        $maxDays = (int) ($rule->trigger_config['max_days'] ?? 0);
        $operator = (string) ($rule->trigger_config['operator'] ?? 'lt');

        if ($maxDays <= 0) {
            return false;
        }

        $context = $entity->getPurchaseContext();
        $nearestCheckIn = $this->resolveNearestCheckIn($context);

        if (! $nearestCheckIn) {
            return false;
        }

        $diffInDays = CarbonImmutable::now()->startOfDay()->diffInDays($nearestCheckIn->startOfDay(), absolute: false);
        // Future dates yield positive diff; past dates negative
        if ($diffInDays < 0) {
            $diffInDays = 0;
        }

        return match ($operator) {
            'lt' => $diffInDays < $maxDays,
            'lte' => $diffInDays <= $maxDays,
            'gt' => $diffInDays > $maxDays,
            'gte' => $diffInDays >= $maxDays,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveNearestCheckIn(array $context): ?CarbonImmutable
    {
        if (isset($context['nearest_check_in']) && $context['nearest_check_in']) {
            try {
                return CarbonImmutable::parse($context['nearest_check_in']);
            } catch (\Throwable) {
                return null;
            }
        }

        $checkInDates = $context['check_in_dates'] ?? [];

        if (empty($checkInDates) || ! is_array($checkInDates)) {
            return null;
        }

        $earliest = null;

        foreach ($checkInDates as $date) {
            try {
                $parsed = CarbonImmutable::parse($date);
                if ($earliest === null || $parsed->lessThan($earliest)) {
                    $earliest = $parsed;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $earliest;
    }
}
