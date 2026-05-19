<?php

namespace App\Services\Promotion;

use App\Contracts\Pricing\PenaltyEvaluator;
use App\Enums\PenaltyTriggerType;
use App\Services\Promotion\Evaluators\BookingWindowEvaluator;
use App\Services\Promotion\Evaluators\CancellationWindowEvaluator;
use App\Services\Promotion\Evaluators\DateRangeEvaluator;
use App\Services\Promotion\Evaluators\EventPeriodEvaluator;
use App\Services\Promotion\Evaluators\LeadTimeEvaluator;

class PenaltyEvaluatorRegistry
{
    /**
     * @var array<string, class-string<PenaltyEvaluator>>
     */
    private array $map = [
        PenaltyTriggerType::BookingWindow->value => BookingWindowEvaluator::class,
        PenaltyTriggerType::EventPeriod->value => EventPeriodEvaluator::class,
        PenaltyTriggerType::DateRange->value => DateRangeEvaluator::class,
        PenaltyTriggerType::LeadTime->value => LeadTimeEvaluator::class,
        PenaltyTriggerType::CancellationWindow->value => CancellationWindowEvaluator::class,
    ];

    public function for(PenaltyTriggerType|string $triggerType): PenaltyEvaluator
    {
        $key = $triggerType instanceof PenaltyTriggerType ? $triggerType->value : $triggerType;

        if (! isset($this->map[$key])) {
            throw new \InvalidArgumentException("No evaluator registered for trigger type [{$key}].");
        }

        return app($this->map[$key]);
    }

    public function has(PenaltyTriggerType|string $triggerType): bool
    {
        $key = $triggerType instanceof PenaltyTriggerType ? $triggerType->value : $triggerType;

        return isset($this->map[$key]);
    }
}
