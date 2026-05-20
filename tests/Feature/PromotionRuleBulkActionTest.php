<?php

use App\Models\PromotionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['promotion_rules.read', 'promotion_rules.create', 'promotion_rules.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->givePermissionTo([
        'promotion_rules.read',
        'promotion_rules.create',
        'promotion_rules.delete',
    ]);
});

it('bulk deletes selected promotion rules', function () {
    $rules = PromotionRule::factory()->count(3)->create();
    $ids = $rules->pluck('id')->all();

    $response = $this->actingAs($this->user)
        ->deleteJson('/api/promotion-rules/bulk', ['ids' => $ids]);

    $response->assertOk()
        ->assertJson(['deleted_count' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('promotion_rules', ['id' => $id]);
    }
});

it('requires ids for bulk delete', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/promotion-rules/bulk', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

it('validates ids exist for bulk delete', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/promotion-rules/bulk', ['ids' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids.0');
});

it('denies bulk delete without permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $rule = PromotionRule::factory()->create();

    $this->actingAs($user)
        ->deleteJson('/api/promotion-rules/bulk', ['ids' => [$rule->id]])
        ->assertStatus(403);

    $this->assertDatabaseHas('promotion_rules', ['id' => $rule->id, 'deleted_at' => null]);
});

it('exports promotion rules as an xlsx download', function () {
    PromotionRule::factory()->count(2)->create();

    $response = $this->actingAs($this->user)->get('/api/promotion-rules/export');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('promotion_rules_')
        ->toContain('.xlsx');
});

it('denies export without permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/api/promotion-rules/export')
        ->assertStatus(403);
});
