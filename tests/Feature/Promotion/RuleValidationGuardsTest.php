<?php

use App\Models\PromotionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'promotion_rules.create']);
    Permission::firstOrCreate(['name' => 'promotion_rules.update']);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->givePermissionTo(['promotion_rules.create', 'promotion_rules.update']);
});

it('rejects penalty kind with conditional value_type', function () {
    $payload = [
        'name' => 'Penalty BOGO',
        'kind' => 'penalty',
        'value_type' => 'buy_x_get_y',
        'value' => 0,
        'value_config' => ['buy_qty' => 1, 'get_free_qty' => 1],
        'stacking_mode' => 'exclusive',
        'trigger_type' => 'manual',
    ];

    $response = $this->actingAs($this->user)->postJson('/api/promotion-rules', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('value_type');
});

it('accepts penalty kind with percentage value_type', function () {
    $payload = [
        'name' => 'Late Fee 5%',
        'kind' => 'penalty',
        'value_type' => 'percentage',
        'value' => 5,
        'stacking_mode' => 'combinable_with_all',
        'trigger_type' => 'manual',
    ];

    $response = $this->actingAs($this->user)->postJson('/api/promotion-rules', $payload);

    $response->assertStatus(201);
});

it('rejects update that flips discount->penalty without changing buy_x_get_y value_type', function () {
    $rule = PromotionRule::factory()->buyXGetY(1, 1)->create();

    $response = $this->actingAs($this->user)->patchJson("/api/promotion-rules/{$rule->ulid}", [
        'kind' => 'penalty',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('value_type');
});

it('rejects update that sets value > 100 on existing percentage discount rule', function () {
    // B7: validator must read existing rule state when value_type not in payload.
    $rule = PromotionRule::factory()->percentage(10)->create();

    $response = $this->actingAs($this->user)->patchJson("/api/promotion-rules/{$rule->ulid}", [
        'value' => 150,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('value');
});

it('rejects update that removes required buy_qty on existing buy_x_get_y rule', function () {
    // B7: validator must read existing value_type to enforce value_config requirements
    $rule = PromotionRule::factory()->buyXGetY(2, 1)->create();

    $response = $this->actingAs($this->user)->patchJson("/api/promotion-rules/{$rule->ulid}", [
        'value_config' => ['get_free_qty' => 1],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('value_config.buy_qty');
});
