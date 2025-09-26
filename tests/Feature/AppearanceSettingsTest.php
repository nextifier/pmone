<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can update theme settings', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_settings' => ['theme' => 'light'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => [
                'theme' => 'dark',
            ],
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'settings',
        ]);

    $user->refresh();
    expect($user->user_settings['theme'])->toBe('dark');
});

test('can set system theme preference', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => [
                'theme' => 'system',
                'language' => 'en',
            ],
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->user_settings['theme'])->toBe('system');
    expect($user->user_settings['language'])->toBe('en');
});
