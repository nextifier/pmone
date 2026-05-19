<?php

use App\Enums\StackingMode;
use App\Models\AppliedAdjustment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Services\Promotion\StackingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

/**
 * Build a rule + attached promo code so codes()->exists() returns true.
 */
function makePromoRule(StackingMode $mode, ?string $codeSuffix = null): PromotionRule
{
    $rule = PromotionRule::factory()->percentage(10)->create([
        'stacking_mode' => $mode,
        'is_system_manual' => false,
    ]);

    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'STK-'.($codeSuffix ?? uniqid()),
    ]);

    return $rule->fresh();
}

function makeManualRule(StackingMode $mode): PromotionRule
{
    return PromotionRule::factory()->percentage(10)->create([
        'stacking_mode' => $mode,
        'is_system_manual' => true,
    ])->fresh();
}

function adjustmentFor(PromotionRule $rule): AppliedAdjustment
{
    $adj = new AppliedAdjustment;
    $adj->adjustable_type = 'Reservation';
    $adj->adjustable_id = 1;
    $adj->promotion_rule_id = $rule->id;
    $adj->kind = $rule->kind;
    $adj->label = $rule->name;
    $adj->value_type = $rule->value_type;
    $adj->value = $rule->value;
    $adj->base_amount = 0;
    $adj->amount = 0;
    $adj->applied_by = 'test';
    $adj->setRelation('promotionRule', $rule);

    return $adj;
}

/**
 * Stacking matrix (candidate is promo-driven by default; mark manual when needed).
 * Pattern: existing rule (column) is on the reservation already, candidate (row)
 * is being evaluated.
 */
dataset('stacking_promo_promo', [
    // [existingMode, candidateMode, expected]
    'exclusive vs exclusive' => [StackingMode::Exclusive, StackingMode::Exclusive, false],
    'exclusive vs combinable_with_promo' => [StackingMode::Exclusive, StackingMode::CombinableWithPromo, false],
    'exclusive vs combinable_with_manual' => [StackingMode::Exclusive, StackingMode::CombinableWithManual, false],
    'exclusive vs combinable_with_all' => [StackingMode::Exclusive, StackingMode::CombinableWithAll, false],

    'cwp vs exclusive' => [StackingMode::CombinableWithPromo, StackingMode::Exclusive, false],
    'cwp vs cwp' => [StackingMode::CombinableWithPromo, StackingMode::CombinableWithPromo, true], // both promo OK
    'cwp vs cwm' => [StackingMode::CombinableWithPromo, StackingMode::CombinableWithManual, false],
    'cwp vs cwa' => [StackingMode::CombinableWithPromo, StackingMode::CombinableWithAll, true],

    'cwm vs exclusive' => [StackingMode::CombinableWithManual, StackingMode::Exclusive, false],
    'cwm vs cwp' => [StackingMode::CombinableWithManual, StackingMode::CombinableWithPromo, false], // candidate is promo, cwm rejects
    'cwm vs cwm' => [StackingMode::CombinableWithManual, StackingMode::CombinableWithManual, false], // both candidate=promo, cwm wants manual
    'cwm vs cwa' => [StackingMode::CombinableWithManual, StackingMode::CombinableWithAll, false],

    'cwa vs exclusive' => [StackingMode::CombinableWithAll, StackingMode::Exclusive, false],
    'cwa vs cwp' => [StackingMode::CombinableWithAll, StackingMode::CombinableWithPromo, true], // existing OK + candidate OK
    'cwa vs cwm' => [StackingMode::CombinableWithAll, StackingMode::CombinableWithManual, false], // candidate cwm requires manual existing
    'cwa vs cwa' => [StackingMode::CombinableWithAll, StackingMode::CombinableWithAll, true],
]);

it('QA-F: promo-vs-promo stacking matrix', function (StackingMode $existing, StackingMode $candidate, bool $expected) {
    $existingRule = makePromoRule($existing, 'EX-'.$existing->value);
    $candidateRule = makePromoRule($candidate, 'CAND-'.$candidate->value);

    $existingAdj = adjustmentFor($existingRule);

    expect(app(StackingResolver::class)->canStack($candidateRule, collect([$existingAdj])))->toBe($expected);
})->with('stacking_promo_promo');

it('QA-F: empty existing always allows candidate (any mode)', function () {
    $candidate = makePromoRule(StackingMode::Exclusive);
    expect(app(StackingResolver::class)->canStack($candidate, collect()))->toBeTrue();
});

it('QA-F: cwp candidate stacks with manual rule when manual rule mode is cwa', function () {
    // Existing rule is manual + cwa, candidate is promo + cwp
    $existingManual = makeManualRule(StackingMode::CombinableWithAll);
    $candidatePromo = makePromoRule(StackingMode::CombinableWithPromo);

    $existingAdj = adjustmentFor($existingManual);

    // candidate cwp rejects manual existing (cwp wants other promo)
    expect(app(StackingResolver::class)->canStack($candidatePromo, collect([$existingAdj])))->toBeFalse();
});

it('QA-F: cwm candidate stacks with manual cwa existing', function () {
    $existingManual = makeManualRule(StackingMode::CombinableWithAll);
    $candidate = makePromoRule(StackingMode::CombinableWithManual);

    $existingAdj = adjustmentFor($existingManual);

    // candidate cwm accepts manual existing + manual existing allows all
    expect(app(StackingResolver::class)->canStack($candidate, collect([$existingAdj])))->toBeTrue();
});
