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

test('can update appearance design tokens', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => [
                'appearance' => [
                    'baseColor' => 'stone',
                    'theme' => 'blue',
                    'chartColor' => 'emerald',
                ],
            ],
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->user_settings['appearance']['baseColor'])->toBe('stone');
    expect($user->user_settings['appearance']['theme'])->toBe('blue');
    expect($user->user_settings['appearance']['chartColor'])->toBe('emerald');
});

test('rejects invalid appearance values', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => [
                'appearance' => ['baseColor' => 'rainbow'],
            ],
        ])
        ->assertStatus(422);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => [
                'appearance' => ['theme' => 'not-a-theme'],
            ],
        ])
        ->assertStatus(422);
});

test('appearance preference merges with existing settings', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_settings' => ['theme' => 'dark', 'language' => 'en'],
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => [
                'appearance' => ['baseColor' => 'zinc', 'theme' => 'violet'],
            ],
        ])
        ->assertOk();

    $user->refresh();
    // color-mode theme + language preserved, appearance added
    expect($user->user_settings['theme'])->toBe('dark');
    expect($user->user_settings['language'])->toBe('en');
    expect($user->user_settings['appearance']['baseColor'])->toBe('zinc');
});

test('can set appearance radius', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['radius' => 'large']],
        ])
        ->assertOk();

    $user->refresh();
    expect($user->user_settings['appearance']['radius'])->toBe('large');
});

test('can set appearance font and heading font', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['font' => 'geist', 'fontHeading' => 'playfair-display']],
        ])
        ->assertOk();

    $user->refresh();
    expect($user->user_settings['appearance']['font'])->toBe('geist');
    expect($user->user_settings['appearance']['fontHeading'])->toBe('playfair-display');
});

test('accepts default font and inherit heading', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['font' => 'default', 'fontHeading' => 'inherit']],
        ])
        ->assertOk();

    $user->refresh();
    expect($user->user_settings['appearance']['font'])->toBe('default');
    expect($user->user_settings['appearance']['fontHeading'])->toBe('inherit');
});

test('rejects invalid font values', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['font' => 'comic-sans']],
        ])
        ->assertStatus(422);

    // "inherit" is only valid for fontHeading, not the body font.
    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['font' => 'inherit']],
        ])
        ->assertStatus(422);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['fontHeading' => 'wingdings']],
        ])
        ->assertStatus(422);
});

test('rejects invalid radius and chartColor', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['radius' => 'huge']],
        ])
        ->assertStatus(422);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['chartColor' => 'rainbow']],
        ])
        ->assertStatus(422);
});

test('can clear appearance with null', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_settings' => ['appearance' => ['baseColor' => 'zinc', 'theme' => 'blue']],
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => null],
        ])
        ->assertOk();

    $user->refresh();
    expect($user->user_settings['appearance'])->toBeNull();
});

test('partial appearance deep-merges and preserves sibling keys', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_settings' => [
            'appearance' => ['baseColor' => 'neutral', 'theme' => 'neutral', 'chartColor' => 'neutral'],
        ],
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => ['appearance' => ['theme' => 'blue']],
        ])
        ->assertOk();

    $user->refresh();
    // theme updated, baseColor + chartColor preserved (deep merge, not clobbered)
    expect($user->user_settings['appearance']['theme'])->toBe('blue');
    expect($user->user_settings['appearance']['baseColor'])->toBe('neutral');
    expect($user->user_settings['appearance']['chartColor'])->toBe('neutral');
});

test('settings update persists only validated keys', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/user/settings', [
            'settings' => [
                'theme' => 'dark',
                'evil' => 'arbitrary-unvalidated-value',
            ],
        ])
        ->assertOk();

    $user->refresh();
    expect($user->user_settings['theme'])->toBe('dark');
    expect($user->user_settings)->not->toHaveKey('evil');
});
