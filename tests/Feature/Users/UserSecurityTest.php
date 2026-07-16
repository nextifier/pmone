<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

it('returns login history gated by view_security', function () {
    $viewer = securityTestUser(['users.view_security']);
    $target = User::factory()->create();

    activity('auth')->event('login_failed')->performedOn($target)
        ->withProperties(['email' => $target->email])->log('Failed login attempt');

    actingAs($viewer)->getJson("/api/users/{$target->username}/login-history")
        ->assertOk()
        ->assertJsonPath('data.0.event', 'login_failed');

    actingAs(securityTestUser())->getJson("/api/users/{$target->username}/login-history")
        ->assertForbidden();
});

it('returns the sign-ins the user caused, including the oauth provider suffix', function () {
    $viewer = securityTestUser(['users.view_security']);
    $target = User::factory()->create();

    activity()->causedBy($target)->performedOn($target)->log('User logged in');
    activity()->causedBy($target)->performedOn($target)->log('User logged in via google');
    activity()->causedBy($target)->event('created')->log('Created a project');

    $descriptions = actingAs($viewer)->getJson("/api/users/{$target->username}/login-history")
        ->assertOk()
        ->json('data.*.description');

    expect($descriptions)->toHaveCount(2);
    expect($descriptions)->toContain('User logged in', 'User logged in via google');
    expect($descriptions)->not->toContain('Created a project');
});

it('resets a users two-factor and writes an audit log', function () {
    $admin = securityTestUser(['users.reset_2fa']);
    $target = User::factory()->create(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()]);

    actingAs($admin)->deleteJson("/api/users/{$target->username}/two-factor")->assertOk();

    $target->refresh();
    expect($target->two_factor_secret)->toBeNull()
        ->and($target->two_factor_confirmed_at)->toBeNull()
        ->and(Activity::query()->where('event', 'two_factor_reset')->where('subject_id', $target->id)->exists())->toBeTrue();
});

it('forbids a non-master from resetting a master 2FA', function () {
    $admin = securityTestUser(['users.reset_2fa']);
    $master = User::factory()->create(['two_factor_secret' => 'secret']);
    $master->assignRole('master');

    actingAs($admin)->deleteJson("/api/users/{$master->username}/two-factor")->assertForbidden();

    expect($master->fresh()->two_factor_secret)->not->toBeNull();
});

it('sends a password reset link', function () {
    Notification::fake();
    $admin = securityTestUser(['users.send_account_emails']);
    $target = User::factory()->create();

    actingAs($admin)->postJson("/api/users/{$target->username}/send-password-reset")->assertOk();

    Notification::assertSentTo($target, ResetPassword::class);
});

it('resends the verification email only when unverified', function () {
    Notification::fake();
    $admin = securityTestUser(['users.send_account_emails']);
    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create(['email_verified_at' => now()]);

    actingAs($admin)->postJson("/api/users/{$unverified->username}/resend-verification")->assertOk();
    Notification::assertSentTo($unverified, VerifyEmail::class);

    actingAs($admin)->postJson("/api/users/{$verified->username}/resend-verification")->assertStatus(422);
});

it('suspends a user, forcing logout, then reactivates', function () {
    $admin = securityTestUser(['users.suspend']);
    $target = User::factory()->create(['status' => 'active']);
    DB::table('sessions')->insert([
        'id' => 'sess', 'user_id' => $target->id, 'ip_address' => '1.1.1.1',
        'user_agent' => 'x', 'payload' => 'p', 'last_activity' => now()->getTimestamp(),
    ]);
    $target->createToken('mobile');

    actingAs($admin)->postJson("/api/users/{$target->username}/suspend", ['reason' => 'Abuse'])->assertOk();

    $target->refresh();
    expect($target->status)->toBe('inactive')
        ->and($target->suspended_at)->not->toBeNull()
        ->and($target->suspension_reason)->toBe('Abuse')
        ->and($target->suspended_by)->toBe($admin->id)
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0)
        ->and($target->tokens()->count())->toBe(0);

    actingAs($admin)->postJson("/api/users/{$target->username}/unsuspend")->assertOk();
    expect($target->fresh()->status)->toBe('active')
        ->and($target->fresh()->suspended_at)->toBeNull();
});

it('requires a reason to suspend', function () {
    $admin = securityTestUser(['users.suspend']);
    $target = User::factory()->create();

    actingAs($admin)->postJson("/api/users/{$target->username}/suspend", [])->assertStatus(422);
});

it('cannot suspend yourself', function () {
    $admin = securityTestUser(['users.suspend']);

    actingAs($admin)->postJson("/api/users/{$admin->username}/suspend", ['reason' => 'x'])->assertForbidden();
});

it('returns user stats', function () {
    $admin = securityTestUser();
    User::factory()->count(3)->create(['email_verified_at' => now()]);

    actingAs($admin)->getJson('/api/users/stats')
        ->assertOk()
        ->assertJsonStructure(['data' => ['total', 'online_now', 'verified', 'verified_percent', 'new_this_week', 'per_role']]);
});

it('scopes stats to the role and exclude_role filters', function () {
    $admin = securityTestUser(); // seeded with the "user" role

    $visitor = User::factory()->create();
    $visitor->assignRole('user');

    $exhibitor = User::factory()->create();
    $exhibitor->assignRole('exhibitor');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    // role=user -> the acting admin + the visitor
    actingAs($admin)->getJson('/api/users/stats?role=user')
        ->assertOk()
        ->assertJsonPath('data.total', 2);

    // role=exhibitor -> just the one exhibitor
    actingAs($admin)->getJson('/api/users/stats?role=exhibitor')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    // exclude_role=exhibitor,user -> only the staff account survives
    actingAs($admin)->getJson('/api/users/stats?exclude_role=exhibitor,user')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});
