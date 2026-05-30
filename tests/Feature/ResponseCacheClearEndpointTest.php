<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

test('a master can flush the response cache', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');

    $spy = ResponseCache::spy();

    $this->actingAs($user)
        ->postJson(route('system.response-cache.clear'))
        ->assertOk()
        ->assertJsonPath('message', 'Response cache cleared successfully.');

    $spy->shouldHaveReceived('clear');
});

test('a user without admin.settings permission cannot flush the response cache', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->postJson(route('system.response-cache.clear'))
        ->assertForbidden();
});

test('a guest cannot flush the response cache', function () {
    $this->postJson(route('system.response-cache.clear'))
        ->assertUnauthorized();
});
