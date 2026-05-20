<?php

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['promo_codes.read', 'promo_codes.create', 'promo_codes.delete', 'promo_codes.restore'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->givePermissionTo([
        'promo_codes.read',
        'promo_codes.create',
        'promo_codes.delete',
        'promo_codes.restore',
    ]);
});

it('bulk deletes selected promo codes', function () {
    $codes = PromoCode::factory()->count(3)->create();
    $ids = $codes->pluck('id')->all();

    $response = $this->actingAs($this->user)
        ->deleteJson('/api/promo-codes/bulk', ['ids' => $ids]);

    $response->assertOk()
        ->assertJson(['deleted_count' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('promo_codes', ['id' => $id]);
    }
});

it('requires ids for bulk delete', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/promo-codes/bulk', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

it('validates ids exist for bulk delete', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/promo-codes/bulk', ['ids' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids.0');
});

it('denies bulk delete without permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $code = PromoCode::factory()->create();

    $this->actingAs($user)
        ->deleteJson('/api/promo-codes/bulk', ['ids' => [$code->id]])
        ->assertStatus(403);

    $this->assertDatabaseHas('promo_codes', ['id' => $code->id, 'deleted_at' => null]);
});

it('exports promo codes as an xlsx download', function () {
    PromoCode::factory()->count(2)->create();

    $response = $this->actingAs($this->user)->get('/api/promo-codes/export');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('promo_codes_')
        ->toContain('.xlsx');
});

it('denies export without permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/api/promo-codes/export')
        ->assertStatus(403);
});

it('lists trashed promo codes', function () {
    $code = PromoCode::factory()->create();
    $code->delete();

    $this->actingAs($this->user)
        ->getJson('/api/promo-codes/trash')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $code->id);
});

it('restores a trashed promo code', function () {
    $code = PromoCode::factory()->create();
    $code->delete();

    $this->actingAs($this->user)
        ->postJson("/api/promo-codes/{$code->ulid}/restore")
        ->assertOk();

    $this->assertDatabaseHas('promo_codes', ['id' => $code->id, 'deleted_at' => null]);
});
