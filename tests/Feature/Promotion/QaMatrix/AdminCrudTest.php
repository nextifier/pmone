<?php

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\PromotionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    foreach ([
        'promotion_rules.create', 'promotion_rules.read', 'promotion_rules.update',
        'promotion_rules.delete', 'promotion_rules.restore',
        'promo_codes.create', 'promo_codes.read', 'promo_codes.update', 'promo_codes.delete',
        'promotions.bulk_generate_codes', 'promotions.view_reports',
    ] as $p) {
        Permission::firstOrCreate(['name' => $p]);
    }

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->givePermissionTo([
        'promotion_rules.create', 'promotion_rules.read', 'promotion_rules.update',
        'promotion_rules.delete', 'promotion_rules.restore',
        'promo_codes.create', 'promo_codes.read', 'promo_codes.update', 'promo_codes.delete',
        'promotions.bulk_generate_codes', 'promotions.view_reports',
    ]);
});

// =====================================================
// L1-L3. CRUD rule
// =====================================================
it('QA-L1: POST /api/promotion-rules creates rule for percentage', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/promotion-rules', [
        'name' => 'New Year Promo',
        'kind' => 'discount',
        'value_type' => 'percentage',
        'value' => 15,
        'stacking_mode' => 'combinable_with_all',
        'trigger_type' => 'none',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'New Year Promo')
        ->assertJsonPath('data.value', 15);
});

it('QA-L1: POST creates BOGO rule with value_config', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/promotion-rules', [
        'name' => 'BOGO Promo',
        'kind' => 'discount',
        'value_type' => 'buy_x_get_y',
        'value' => 0,
        'value_config' => ['buy_qty' => 2, 'get_free_qty' => 1],
        'stacking_mode' => 'exclusive',
        'trigger_type' => 'none',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.value_config.buy_qty', 2)
        ->assertJsonPath('data.value_config.get_free_qty', 1);
});

it('QA-L1: rejects rule without permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->postJson('/api/promotion-rules', [
        'name' => 'X', 'kind' => 'discount', 'value_type' => 'percentage', 'value' => 10,
        'stacking_mode' => 'exclusive', 'trigger_type' => 'none',
    ]);

    $response->assertStatus(403);
});

it('QA-L2: PATCH /api/promotion-rules/{ulid} updates rule', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();

    $response = $this->actingAs($this->admin)->patchJson("/api/promotion-rules/{$rule->ulid}", [
        'value' => 20,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.value', 20);
});

it('QA-L3: DELETE soft-deletes + restore brings it back', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();

    $this->actingAs($this->admin)->deleteJson("/api/promotion-rules/{$rule->ulid}")->assertOk();
    expect($rule->fresh()->deleted_at)->not->toBeNull();

    $this->actingAs($this->admin)->postJson("/api/promotion-rules/{$rule->ulid}/restore")->assertOk();
    expect($rule->fresh()->deleted_at)->toBeNull();
});

// =====================================================
// L4. Single code create
// =====================================================
it('QA-L4: POST /api/promotion-rules/{rule}/codes creates single code', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();

    $response = $this->actingAs($this->admin)->postJson("/api/promotion-rules/{$rule->ulid}/codes", [
        'code' => 'singlecode',
        'usage_limit' => 5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'SINGLECODE'); // uppercased on save
});

// =====================================================
// L5. Bulk generate
// =====================================================
it('QA-L5: POST bulk endpoint generates 100 unique codes', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();

    $response = $this->actingAs($this->admin)->postJson("/api/promotion-rules/{$rule->ulid}/codes/bulk", [
        'quantity' => 100,
        'length' => 8,
        'prefix' => 'BULK-',
    ]);

    $response->assertCreated();
    $codes = PromoCode::where('promotion_rule_id', $rule->id)->pluck('code');
    expect($codes)->toHaveCount(100)
        ->and($codes->unique())->toHaveCount(100);
});

// =====================================================
// L6. Report stats
// =====================================================
it('QA-L6: GET /api/promotion-rules/{ulid}/report returns stats', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->count(3)->create(['usage_count' => 0]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'USED', 'usage_count' => 2]);

    $response = $this->actingAs($this->admin)->getJson("/api/promotion-rules/{$rule->ulid}/report");

    $response->assertOk()
        ->assertJsonPath('data.stats.codes_issued', 4)
        ->assertJsonPath('data.stats.codes_used', 1);
});

// =====================================================
// L7. Usages ledger pagination
// =====================================================
it('QA-L7: GET /api/promo-codes/{ulid}/usages paginates', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'LEDGER']);

    foreach (range(1, 5) as $i) {
        PromoCodeUsage::query()->create([
            'promo_code_id' => $code->id,
            'adjustable_type' => 'Reservation',
            'adjustable_id' => $i,
            'email' => "user{$i}@example.com",
            'amount_discounted' => 1000 * $i,
        ]);
    }

    $response = $this->actingAs($this->admin)->getJson("/api/promo-codes/{$code->ulid}/usages?per_page=3");

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 5);
});
