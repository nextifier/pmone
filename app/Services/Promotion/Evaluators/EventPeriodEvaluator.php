<?php

namespace App\Services\Promotion\Evaluators;

use App\Contracts\Pricing\PenaltyEvaluator;
use App\Contracts\Pricing\Purchasable;
use App\Models\Event;
use App\Models\PromotionRule;

/**
 * Backward-compat trigger that reads Event's normal_order_* / onsite_order_* columns.
 *
 * trigger_config: { "phase": "normal" | "onsite" }
 *
 * Used by the seeded rule replacing Event.onsite_penalty_rate.
 */
class EventPeriodEvaluator implements PenaltyEvaluator
{
    public function shouldApply(PromotionRule $rule, Purchasable $entity): bool
    {
        $phase = $rule->trigger_config['phase'] ?? null;

        if (! in_array($phase, ['normal', 'onsite'], true)) {
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

        $opensField = "{$phase}_order_opens_at";
        $closesField = "{$phase}_order_closes_at";

        $opens = $event->{$opensField} ?? null;
        $closes = $event->{$closesField} ?? null;

        $now = now();

        if ($opens && $now->lessThan($opens)) {
            return false;
        }

        if ($closes && $now->greaterThan($closes)) {
            return false;
        }

        return $opens !== null || $closes !== null;
    }
}
