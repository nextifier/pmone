<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->scenario = qaScenario();
    $this->scenario['hotel']->update(['tax_percentage' => 0]);
});

// =====================================================
// G. Usage limits
// =====================================================
it('QA-G: usage_limit=1 second redemption rejected', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'ONCE',
        'usage_limit' => 1,
    ]);

    $r1 = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);
    $r2 = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    app(PromoCodeService::class)->applyByCode('ONCE', $r1, 'first@example.com');

    expect(fn () => app(PromoCodeService::class)->applyByCode('ONCE', $r2, 'second@example.com'))
        ->toThrow(ValidationException::class);
});

it('QA-G: usage_limit=null unlimited redemption ok', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'UNLIM',
        'usage_limit' => null,
        'usage_limit_per_email' => null,
    ]);

    foreach (range(1, 5) as $i) {
        $r = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);
        app(PromoCodeService::class)->applyByCode('UNLIM', $r, "user{$i}@example.com");
    }

    expect(PromoCode::where('code', 'UNLIM')->first()->usage_count)->toBe(5);
});

it('QA-G: usage_limit_per_email=1 same email second redemption rejected', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'PEREMAIL',
        'usage_limit' => 10,
        'usage_limit_per_email' => 1,
    ]);

    $r1 = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);
    $r2 = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    app(PromoCodeService::class)->applyByCode('PEREMAIL', $r1, 'same@example.com');

    $result = app(PromoCodeService::class)->validate('PEREMAIL', $r2, 'same@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_ALREADY_USED);
});

// =====================================================
// H. Window precedence (code vs rule)
// =====================================================
it('QA-H: code valid_until past -> EXPIRED', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'PAST',
        'valid_until' => now()->subDay(),
    ]);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('PAST', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_EXPIRED);
});

it('QA-H: code valid_from future -> NOT_YET_VALID', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'FUTURE',
        'valid_from' => now()->addDay(),
    ]);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('FUTURE', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_NOT_YET_VALID);
});

it('QA-H: tighter rule.ends_at takes precedence even when code.valid_until is later', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'ends_at' => now()->subHour(), // rule already expired
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'RULETIGHT',
        'valid_until' => now()->addDays(7), // code looks valid in isolation
    ]);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('RULETIGHT', $reservation, 'qa@example.com');

    // resolveWindowEnd picks the smaller (rule.ends_at) -> EXPIRED
    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_EXPIRED);
});

it('QA-H: rule starts_at + code valid_from -> later one wins -> NOT_YET_VALID', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'starts_at' => now()->addDays(7), // rule starts later
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'STARTLATE',
        'valid_from' => now()->subDay(), // code is already active
    ]);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('STARTLATE', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_NOT_YET_VALID);
});

it('QA-H: rule.is_active=false -> INACTIVE', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['is_active' => false]);
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'RULEOFF',
        'is_active' => true,
    ]);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('RULEOFF', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_INACTIVE);
});

it('QA-G: revert_usage_on_cancel false preserves counter on void', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'revert_usage_on_cancel' => false,
    ]);
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'NOREVERT',
        'usage_limit' => 5,
    ]);

    $r = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);
    $adj = app(PromoCodeService::class)->applyByCode('NOREVERT', $r, 'qa@example.com');

    expect((int) $code->fresh()->usage_count)->toBe(1);

    app(PromoCodeService::class)->void($adj, 'test');

    // Counter preserved
    expect((int) $code->fresh()->usage_count)->toBe(1);
});
