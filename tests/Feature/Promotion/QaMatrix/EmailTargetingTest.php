<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
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
// I. Email targeting
// =====================================================
it('QA-I: issued_to_email mismatch -> NOT_ELIGIBLE', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'VIP',
        'issued_to_email' => 'vip@askindo.com',
    ]);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('VIP', $reservation, 'other@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_NOT_ELIGIBLE);
});

it('QA-I: issued_to_email match (case-insensitive)', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'VIPMATCH',
        'issued_to_email' => 'VIP@ASKINDO.COM', // stored lowercased by boot()
    ]);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('VIPMATCH', $reservation, 'vip@askindo.com');

    expect($result->valid)->toBeTrue();
});

it('QA-I: guest_email_domains applicability accepts matching domain', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['guest_email_domains' => ['@askindo.com']],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'DOMAINOK']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('DOMAINOK', $reservation, 'guest@askindo.com');

    expect($result->valid)->toBeTrue();
});

it('QA-I: guest_email_domains rejects mismatched domain', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['guest_email_domains' => ['@askindo.com']],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'DOMAINNO']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('DOMAINNO', $reservation, 'guest@gmail.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_DOES_NOT_APPLY);
});

it('QA-I: domain match supports bare domain (no @) prefix', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['guest_email_domains' => ['askindo.com']], // no leading @
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BAREDOM']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('BAREDOM', $reservation, 'guest@askindo.com');

    expect($result->valid)->toBeTrue();
});

it('QA-I: first_purchase_only rejects when prior usage exists for email', function () {
    // Seed prior usage
    $priorRule = PromotionRule::factory()->percentage(5)->create();
    $priorCode = PromoCode::factory()->for($priorRule, 'promotionRule')->create(['code' => 'PRIOR']);

    PromoCodeUsage::query()->create([
        'promo_code_id' => $priorCode->id,
        'adjustable_type' => 'Reservation',
        'adjustable_id' => 1,
        'email' => 'repeat@example.com',
        'amount_discounted' => 1000,
    ]);

    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['first_purchase_only' => true],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'FIRSTONLY']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('FIRSTONLY', $reservation, 'repeat@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_DOES_NOT_APPLY);
});

it('QA-I: first_purchase_only accepts new email', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['first_purchase_only' => true],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'NEWFIRST']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('NEWFIRST', $reservation, 'brand-new@example.com');

    expect($result->valid)->toBeTrue();
});
