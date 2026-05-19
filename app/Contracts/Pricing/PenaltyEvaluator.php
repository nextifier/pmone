<?php

namespace App\Contracts\Pricing;

use App\Models\PromotionRule;

/**
 * Strategy interface for penalty rule auto-trigger evaluation.
 */
interface PenaltyEvaluator
{
    public function shouldApply(PromotionRule $rule, Purchasable $entity): bool;
}
