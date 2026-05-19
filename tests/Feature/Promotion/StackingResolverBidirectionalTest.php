<?php

use App\Enums\StackingMode;
use App\Models\AppliedAdjustment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Services\Promotion\StackingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAdjustmentForRule(PromotionRule $rule, ?Reservation $reservation = null): AppliedAdjustment
{
    $adj = new AppliedAdjustment;
    $adj->adjustable_type = 'Reservation';
    $adj->adjustable_id = $reservation?->id ?? 1;
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

it('rejects CombinableWithAll candidate when existing rule is CombinableWithManual and candidate is promo-driven', function () {
    // Existing rule: a system manual rule expects to combine only with other manual rules
    $existingRule = PromotionRule::factory()->percentage(5)->create([
        'stacking_mode' => StackingMode::CombinableWithManual,
        'is_system_manual' => true,
    ]);
    $existingAdj = makeAdjustmentForRule($existingRule);

    // Candidate: CombinableWithAll promo (with a code)
    $candidate = PromotionRule::factory()->percentage(10)->create([
        'stacking_mode' => StackingMode::CombinableWithAll,
        'is_system_manual' => false,
    ]);
    PromoCode::factory()->for($candidate, 'promotionRule')->create(['code' => 'CWAPROMO']);

    $resolver = app(StackingResolver::class);

    expect($resolver->canStack($candidate, collect([$existingAdj])))->toBeFalse();
});

it('allows CombinableWithAll candidate when existing rule is CombinableWithAll', function () {
    $existingRule = PromotionRule::factory()->percentage(5)->create([
        'stacking_mode' => StackingMode::CombinableWithAll,
    ]);
    $existingAdj = makeAdjustmentForRule($existingRule);

    $candidate = PromotionRule::factory()->percentage(10)->create([
        'stacking_mode' => StackingMode::CombinableWithAll,
    ]);

    $resolver = app(StackingResolver::class);

    expect($resolver->canStack($candidate, collect([$existingAdj])))->toBeTrue();
});

it('rejects any candidate when existing rule is Exclusive', function () {
    $existingRule = PromotionRule::factory()->percentage(5)->create([
        'stacking_mode' => StackingMode::Exclusive,
    ]);
    $existingAdj = makeAdjustmentForRule($existingRule);

    $candidate = PromotionRule::factory()->percentage(10)->create([
        'stacking_mode' => StackingMode::CombinableWithAll,
    ]);

    $resolver = app(StackingResolver::class);

    expect($resolver->canStack($candidate, collect([$existingAdj])))->toBeFalse();
});
