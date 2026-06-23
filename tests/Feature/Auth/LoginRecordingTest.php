<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('records last login details on successful password login', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'password' => Hash::make('secret-password'),
        'last_login_at' => null,
        'last_login_ip' => null,
    ]);

    postJson('/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk();

    $user->refresh();

    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull()
        ->and($user->last_seen)->not->toBeNull();
});

it('does not flood the activity log with model-update entries on login', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'password' => Hash::make('secret-password'),
    ]);

    postJson('/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk();

    // Only the explicit "User logged in" entry should exist - the last_login_*
    // columns are not in the model's logged attributes, so updating them logs nothing.
    expect(Activity::query()->where('description', 'User logged in')->count())->toBe(1);
});

it('records a failed login attempt in the auth log', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'password' => Hash::make('correct-password'),
    ]);

    postJson('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422);

    $failed = Activity::query()
        ->where('log_name', 'auth')
        ->where('event', 'login_failed')
        ->first();

    expect($failed)->not->toBeNull()
        ->and($failed->getExtraProperty('email'))->toBe($user->email)
        ->and($failed->subject_id)->toBe($user->id);
});
