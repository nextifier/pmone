<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->scenario = qaScenario();
    $this->scenario['hotel']->update(['tax_percentage' => 0]);
});

// =====================================================
// D. max_discount_amount (cap)
// =====================================================
it('QA-D: percentage 50% cap 100k on 1M subtotal -> 100k discount (not 500k)', function () {
    $rule = PromotionRule::factory()->percentage(50)->create(['max_discount_amount' => 100_000]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'CAP100K']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    app(PromoCodeService::class)->applyByCode('CAP100K', $reservation, 'qa@example.com');

    expect((float) $reservation->fresh()->discount_amount)->toBe(100_000.0);
});

it('QA-D: cap higher than computed discount has no effect', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['max_discount_amount' => 1_000_000]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'CAPHIGH']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    app(PromoCodeService::class)->applyByCode('CAPHIGH', $reservation, 'qa@example.com');

    // 10% of 1M = 100k, well below 1M cap
    expect((float) $reservation->fresh()->discount_amount)->toBe(100_000.0);
});

it('QA-D: null max_discount_amount means no cap', function () {
    $rule = PromotionRule::factory()->percentage(80)->create(['max_discount_amount' => null]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'NOCAP']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    app(PromoCodeService::class)->applyByCode('NOCAP', $reservation, 'qa@example.com');

    expect((float) $reservation->fresh()->discount_amount)->toBe(800_000.0);
});

// =====================================================
// E. min_purchase_amount
// =====================================================
it('QA-E: subtotal < min rejects with MIN_PURCHASE_NOT_MET', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['min_purchase_amount' => 5_000_000]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'MIN5M']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $result = app(PromoCodeService::class)->validate('MIN5M', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_MIN_PURCHASE_NOT_MET);
});

it('QA-E: subtotal exactly equals min passes', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['min_purchase_amount' => 1_000_000]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'MINEXACT']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $result = app(PromoCodeService::class)->validate('MINEXACT', $reservation, 'qa@example.com');

    expect($result->valid)->toBeTrue();
});

it('QA-E: subtotal > min passes', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['min_purchase_amount' => 500_000]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'MINOK']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $result = app(PromoCodeService::class)->validate('MINOK', $reservation, 'qa@example.com');

    expect($result->valid)->toBeTrue();
});

it('QA-E: min_purchase compared against subtotal_rooms+transfer+surcharge (pre-tax)', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['min_purchase_amount' => 1_100_000]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'MINPRETAX']);

    // 1M rooms subtotal would fail with min=1.1M (does NOT include tax)
    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $result = app(PromoCodeService::class)->validate('MINPRETAX', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_MIN_PURCHASE_NOT_MET);
});
