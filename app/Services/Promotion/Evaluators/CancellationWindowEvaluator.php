<?php

namespace App\Services\Promotion\Evaluators;

use App\Contracts\Pricing\PenaltyEvaluator;
use App\Contracts\Pricing\Purchasable;
use App\Models\PromotionRule;
use Carbon\CarbonImmutable;

/**
 * Cancellation fee: fires when cancellation happens within min_days of nearest check-in.
 *
 * trigger_config: { "min_days": int, "operator": "lt" | "lte", "percentage_of_total": float? }
 *
 * Only manually invoked during cancel flow (not auto-iterated).
 */
class CancellationWindowEvaluator implements PenaltyEvaluator
{
    public function shouldApply(PromotionRule $rule, Purchasable $entity): bool
    {
        $minDays = (int) ($rule->trigger_config['min_days'] ?? 0);
        $operator = (string) ($rule->trigger_config['operator'] ?? 'lt');

        if ($minDays <= 0) {
            return false;
        }

        $context = $entity->getPurchaseContext();
        $nearestCheckIn = $this->resolveNearestCheckIn($context);

        if (! $nearestCheckIn) {
            return false;
        }

        $diffInDays = CarbonImmutable::now()->startOfDay()->diffInDays($nearestCheckIn->startOfDay(), absolute: false);

        if ($diffInDays < 0) {
            $diffInDays = 0;
        }

        return match ($operator) {
            'lt' => $diffInDays < $minDays,
            'lte' => $diffInDays <= $minDays,
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
