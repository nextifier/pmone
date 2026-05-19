<?php

namespace App\Services\Promotion\Evaluators;

use App\Contracts\Pricing\PenaltyEvaluator;
use App\Contracts\Pricing\Purchasable;
use App\Models\Event;
use App\Models\PromotionRule;

/**
 * Trigger fires when now() falls within the configured booking window.
 *
 * trigger_config: { "window": "onsite" | "normal" }
 *
 * Maps to Event.{normal,onsite}_order_opens_at / _closes_at columns when available.
 */
class BookingWindowEvaluator implements PenaltyEvaluator
{
    public function shouldApply(PromotionRule $rule, Purchasable $entity): bool
    {
        $window = $rule->trigger_config['window'] ?? null;

        if (! in_array($window, ['onsite', 'normal'], true)) {
            return false;
        }

        $eventId = $entity->getPurchaseContext()['event_id'] ?? $rule->event_id;

        if (! $eventId) {
            return false;
        }

        /** @var Event|null $event */
        $event = Event::query()->find($eventId);

        if (! $event) {
            return false;
        }

        $opensField = "{$window}_order_opens_at";
        $closesField = "{$window}_order_closes_at";

        $opens = $event->{$opensField} ?? null;
        $closes = $event->{$closesField} ?? null;

        $now = now();

        if ($opens && $now->lessThan($opens)) {
            return false;
        }

        if ($closes && $now->greaterThan($closes)) {
            return false;
        }

        if (! $opens && ! $closes) {
            return false;
        }

        return true;
    }
}
