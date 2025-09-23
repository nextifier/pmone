<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('can check password status for user with password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/user/password-status');

    $response->assertOk()
        ->assertJson([
            'has_password' => true,
        ]);
});

test('can check password status for user without password', function () {
    $user = User::factory()->create([
        'password' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/user/password-status');

    $response->assertOk()
        ->assertJson([
            'has_password' => false,
        ]);
});

test('can update password when user has existing password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword123'),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertOk();

    $user->refresh();
    $this->assertTrue(Hash::check('newpassword123', $user->password));
});

test('can set password when user has no existing password', function () {
    $user = User::factory()->create([
        'password' => null,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/password', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertOk();

    $user->refresh();
    $this->assertTrue(Hash::check('newpassword123', $user->password));
});
