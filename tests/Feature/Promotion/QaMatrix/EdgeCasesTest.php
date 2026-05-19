<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\User;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->scenario = qaScenario();
    $this->scenario['hotel']->update(['tax_percentage' => 0]);
});

// =====================================================
// M. Edge cases
// =====================================================
it('QA-M: lowercase code normalizes to uppercase on validate', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'NORMALIZE']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('  normalize  ', $reservation, 'qa@example.com');

    expect($result->valid)->toBeTrue();
});

it('QA-M: code with whitespace trimmed', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'TRIMMED']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate("\tTRIMMED\n", $reservation, 'qa@example.com');

    expect($result->valid)->toBeTrue();
});

it('QA-M: code with illegal characters rejected by FormRequest regex', function () {
    foreach (['promotion_rules.create', 'promo_codes.create'] as $p) {
        Permission::firstOrCreate(['name' => $p]);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['promotion_rules.create', 'promo_codes.create']);

    $rule = PromotionRule::factory()->percentage(10)->create();

    $response = $this->actingAs($admin)->postJson("/api/promotion-rules/{$rule->ulid}/codes", [
        'code' => 'BAD CODE!@#',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('QA-M: soft-deleted rule -> code returns INVALID_CODE', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'ORPHAN']);

    $rule->delete();

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('ORPHAN', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_INVALID_CODE);
});

it('QA-M: soft-deleted code returns INVALID_CODE', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'DELETED']);
    $code->delete();

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('DELETED', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_INVALID_CODE);
});

it('QA-M: apply code then void with revert -> usage_count restored', function () {
    $rule = PromotionRule::factory()->percentage(10)->create(['revert_usage_on_cancel' => true]);
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'VOIDED']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $adj = app(PromoCodeService::class)->applyByCode('VOIDED', $reservation, 'qa@example.com');
    expect((int) $code->fresh()->usage_count)->toBe(1);

    app(PromoCodeService::class)->void($adj, 'qa-test');

    expect((int) $code->fresh()->usage_count)->toBe(0)
        ->and($adj->fresh()->voided_at)->not->toBeNull();
});

it('QA-M: empty code string returns INVALID_CODE', function () {
    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('   ', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_INVALID_CODE);
});

it('QA-M: PromotionRule for Order purchase type rejects Reservation cart', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'target_types' => ['Order'],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'ORDERONLY']);

    $reservation = qaReservation($this->scenario, [['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000]]);

    $result = app(PromoCodeService::class)->validate('ORDERONLY', $reservation, 'qa@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_NOT_APPLICABLE_TO_PURCHASE_TYPE);
});
