<?php

use App\Models\User;
use App\Support\AuthActivity;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * Log every row shape a causer-scoped feed can encounter, and return the causer.
 */
function seedAuthActivityFixtures(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    activity()->causedBy($user)->performedOn($user)->log('User logged in');
    activity()->causedBy($user)->performedOn($user)->log('User logged out');
    activity()->causedBy($user)->performedOn($user)->log('User logged in via magic link');
    activity()->causedBy($user)->performedOn($user)->log('User logged in via google');
    activity()->causedBy($user)->event('created')->log('Created a project');
    activity('auth')->causedBy($user)->log('Something authy');

    DB::table('activity_log')->insert([
        'log_name' => null,
        'description' => 'Legacy row',
        'causer_type' => (new User)->getMorphClass(),
        'causer_id' => $user->id,
        'properties' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

/**
 * @return Builder<Activity>
 */
function causedBySingleUser(User $user)
{
    return Activity::query()
        ->where('causer_type', (new User)->getMorphClass())
        ->where('causer_id', $user->id);
}

it('matches every sign-in description, including the oauth provider suffix', function () {
    $user = seedAuthActivityFixtures();

    $descriptions = AuthActivity::whereCausedLogin(causedBySingleUser($user))
        ->pluck('description')
        ->sort()
        ->values()
        ->all();

    expect($descriptions)->toBe([
        'User logged in',
        'User logged in via google',
        'User logged in via magic link',
        'User logged out',
    ]);
});

it('keeps the two scopes disjoint', function () {
    $user = seedAuthActivityFixtures();

    $login = AuthActivity::whereCausedLogin(causedBySingleUser($user))->pluck('id');
    $notLogin = AuthActivity::whereNotCausedLogin(causedBySingleUser($user))->pluck('id');

    expect($login->intersect($notLogin))->toBeEmpty();
});

it('covers every causer row except the auth channel, which belongs to neither tab', function () {
    $user = seedAuthActivityFixtures();

    $login = AuthActivity::whereCausedLogin(causedBySingleUser($user))->pluck('id');
    $notLogin = AuthActivity::whereNotCausedLogin(causedBySingleUser($user))->pluck('id');

    // The 'auth' channel is the Login History tab's own domain (failed attempts
    // are matched there by subject), so it is deliberately outside both scopes.
    $covered = causedBySingleUser($user)
        ->where(function ($q) {
            $q->whereNull('log_name')->orWhere('log_name', '!=', AuthActivity::LOG_NAME);
        })
        ->pluck('id');

    expect($login->merge($notLogin)->sort()->values()->all())
        ->toBe($covered->sort()->values()->all());
});

it('keeps rows with a null log name out of the auth bucket', function () {
    $user = seedAuthActivityFixtures();

    $descriptions = AuthActivity::whereNotCausedLogin(causedBySingleUser($user))->pluck('description');

    // `log_name != 'auth'` alone evaluates to NULL for these rows and would drop them.
    expect($descriptions)->toContain('Legacy row');
    expect($descriptions)->toContain('Created a project');
    expect($descriptions)->not->toContain('Something authy');
});
