<?php

namespace App\Traits;

use App\Contracts\Pricing\Purchasable;
use App\Models\AppliedAdjustment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Provides polymorphic relationship to AppliedAdjustment and helper accessors.
 *
 * Use together with the {@see Purchasable} contract.
 */
trait HasAdjustments
{
    public function adjustments(): MorphMany
    {
        return $this->morphMany(AppliedAdjustment::class, 'adjustable');
    }

    public function activeAdjustments(): MorphMany
    {
        return $this->adjustments()->whereNull('voided_at');
    }

    public function discountAdjustments(): MorphMany
    {
        return $this->adjustments()
            ->where('kind', 'discount')
            ->whereNull('voided_at');
    }

    public function penaltyAdjustments(): MorphMany
    {
        return $this->adjustments()
            ->where('kind', 'penalty')
            ->whereNull('voided_at');
    }

    public function totalDiscount(): float
    {
        return (float) $this->discountAdjustments()->sum('amount');
    }

    public function totalPenalty(): float
    {
        return (float) $this->penaltyAdjustments()->sum('amount');
    }
}
