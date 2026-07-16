<?php

use App\Models\User;
use App\Models\UserPageView;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('rejects a guest heartbeat', function () {
    postJson('/api/presence/heartbeat', ['path' => '/users', 'navigation' => true])
        ->assertUnauthorized();
});

it('records a navigation heartbeat and normalizes the path', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'last_seen' => now()->subMinutes(10)]);

    actingAs($user)
        ->postJson('/api/presence/heartbeat', [
            'path' => '/users?page=2#x',
            'title' => 'Users',
            'navigation' => true,
        ])
        ->assertNoContent();

    $user->refresh();
    expect($user->last_seen->gt(now()->subMinute()))->toBeTrue();
    expect($user->last_page)->toBe('/users');
    expect($user->last_page_title)->toBe('Users');

    $views = UserPageView::query()->where('user_id', $user->id)->get();
    expect($views)->toHaveCount(1);
    expect($views->first()->path)->toBe('/users');
});

it('updates presence without a page view on a keepalive heartbeat', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)
        ->postJson('/api/presence/heartbeat', [
            'path' => '/dashboard',
            'title' => 'Dashboard',
            'navigation' => false,
        ])
        ->assertNoContent();

    expect($user->refresh()->last_page)->toBe('/dashboard');
    expect(UserPageView::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('validates the heartbeat payload', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)->postJson('/api/presence/heartbeat', ['navigation' => true])
        ->assertJsonValidationErrorFor('path');

    actingAs($user)->postJson('/api/presence/heartbeat', ['path' => 'users', 'navigation' => true])
        ->assertJsonValidationErrorFor('path');

    actingAs($user)->postJson('/api/presence/heartbeat', ['path' => '/users', 'navigation' => 'maybe'])
        ->assertJsonValidationErrorFor('navigation');
});

it('throttles heartbeats past the per-minute ceiling', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    for ($i = 0; $i < 30; $i++) {
        actingAs($user)
            ->postJson('/api/presence/heartbeat', ['path' => '/dashboard', 'navigation' => false])
            ->assertNoContent();
    }

    actingAs($user)
        ->postJson('/api/presence/heartbeat', ['path' => '/dashboard', 'navigation' => false])
        ->assertStatus(429);
});

it('exposes last_page in the users list to master whether the user is online or not', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('master');

    $online = User::factory()->create([
        'email_verified_at' => now(),
        'last_seen' => now(),
        'last_page' => '/dashboard',
        'last_page_title' => 'Dashboard',
    ]);
    $offline = User::factory()->create([
        'email_verified_at' => now(),
        'last_seen' => now()->subMinutes(10),
        'last_page' => '/settings',
        'last_page_title' => 'Settings',
    ]);
    $neverSeen = User::factory()->create(['email_verified_at' => now(), 'last_page' => null]);

    $data = actingAs($admin)->getJson('/api/users')->assertOk()->json('data');

    $onlineRow = collect($data)->firstWhere('id', $online->id);
    $offlineRow = collect($data)->firstWhere('id', $offline->id);
    $neverSeenRow = collect($data)->firstWhere('id', $neverSeen->id);

    expect($onlineRow['last_page']['path'])->toBe('/dashboard');
    expect($offlineRow['last_page']['path'])->toBe('/settings');
    expect($offlineRow['last_page']['title'])->toBe('Settings');
    expect($neverSeenRow)->not->toHaveKey('last_page');
});

it('hides last_page from non-master viewers even when online', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo('users.read');

    $online = User::factory()->create([
        'email_verified_at' => now(),
        'last_seen' => now(),
        'last_page' => '/dashboard',
        'last_page_title' => 'Dashboard',
    ]);

    $data = actingAs($admin)->getJson('/api/users')->assertOk()->json('data');
    $onlineRow = collect($data)->firstWhere('id', $online->id);

    expect($onlineRow)->not->toHaveKey('last_page');
});
