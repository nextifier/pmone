<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function activityLogUrl(User $user): string
{
    return "/api/user-activity/users/{$user->username}/activity-log";
}

it('requires both the analytics and the logs permission', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    getJson(activityLogUrl($target))->assertUnauthorized();

    actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->getJson(activityLogUrl($target))->assertForbidden();

    actingAs(securityTestUser(['users.view_analytics']))
        ->getJson(activityLogUrl($target))->assertForbidden();

    actingAs(securityTestUser(['admin.logs']))
        ->getJson(activityLogUrl($target))->assertForbidden();

    actingAs(securityTestUser(['users.view_analytics', 'admin.logs']))
        ->getJson(activityLogUrl($target))->assertOk();
});

it('excludes the sign-in rows that the login history tab already shows', function () {
    $viewer = securityTestUser(['users.view_analytics', 'admin.logs', 'users.view_security']);
    $target = User::factory()->create(['email_verified_at' => now()]);

    activity()->causedBy($target)->performedOn($target)->log('User logged in');
    activity()->causedBy($target)->performedOn($target)->log('User logged out');
    activity()->causedBy($target)->performedOn($target)->log('User logged in via magic link');
    activity()->causedBy($target)->performedOn($target)->log('User logged in via google');
    activity()->causedBy($target)->event('created')->log('Created a project');

    $activity = actingAs($viewer)->getJson(activityLogUrl($target))->assertOk()->json('data');
    $loginHistory = actingAs($viewer)->getJson("/api/users/{$target->username}/login-history")
        ->assertOk()->json('data');

    // The two tabs partition the rows: no overlap, nothing dropped.
    expect($activity)->toHaveCount(1);
    expect($activity[0]['description'])->toBe('Created a project');
    expect($loginHistory)->toHaveCount(4);
});

it('excludes auth channel rows but keeps rows with a null log name', function () {
    $viewer = securityTestUser(['users.view_analytics', 'admin.logs']);
    $target = User::factory()->create(['email_verified_at' => now()]);

    activity('auth')->causedBy($target)->log('Something authy');
    activity()->causedBy($target)->event('created')->log('Created a project');

    DB::table('activity_log')->insert([
        'log_name' => null,
        'description' => 'Legacy row',
        'causer_type' => (new User)->getMorphClass(),
        'causer_id' => $target->id,
        'properties' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $descriptions = actingAs($viewer)->getJson(activityLogUrl($target))
        ->assertOk()->json('data.*.description');

    // `log_name != 'auth'` alone evaluates to NULL for the legacy row and would drop it.
    expect($descriptions)->toContain('Legacy row');
    expect($descriptions)->toContain('Created a project');
    expect($descriptions)->not->toContain('Something authy');
});

it('does not leak another user activity', function () {
    $viewer = securityTestUser(['users.view_analytics', 'admin.logs']);
    $a = User::factory()->create(['email_verified_at' => now()]);
    $b = User::factory()->create(['email_verified_at' => now()]);

    activity()->causedBy($a)->event('created')->log('A created a post');
    activity()->causedBy($b)->event('created')->log('B created a post');

    $descriptions = actingAs($viewer)->getJson(activityLogUrl($a))->assertOk()->json('data.*.description');

    expect($descriptions)->toBe(['A created a post']);
});

it('orders newest first', function () {
    $viewer = securityTestUser(['users.view_analytics', 'admin.logs']);
    $target = User::factory()->create(['email_verified_at' => now()]);

    activity()->causedBy($target)->event('created')->log('Older');
    $this->travel(2)->minutes();
    activity()->causedBy($target)->event('created')->log('Newer');
    $this->travelBack();

    $descriptions = actingAs($viewer)->getJson(activityLogUrl($target))->assertOk()->json('data.*.description');

    expect($descriptions)->toBe(['Newer', 'Older']);
});

it('clamps per_page at 100 instead of failing', function () {
    $viewer = securityTestUser(['users.view_analytics', 'admin.logs']);
    $target = User::factory()->create(['email_verified_at' => now()]);

    actingAs($viewer)->getJson(activityLogUrl($target).'?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('filters by search', function () {
    $viewer = securityTestUser(['users.view_analytics', 'admin.logs']);
    $target = User::factory()->create(['email_verified_at' => now()]);

    activity()->causedBy($target)->event('created')->log('Created a project');
    activity()->causedBy($target)->event('deleted')->log('Deleted a brand');

    $descriptions = actingAs($viewer)->getJson(activityLogUrl($target).'?search=project')
        ->assertOk()->json('data.*.description');

    expect($descriptions)->toBe(['Created a project']);
});

it('rejects an overlong search term', function () {
    $viewer = securityTestUser(['users.view_analytics', 'admin.logs']);
    $target = User::factory()->create(['email_verified_at' => now()]);

    actingAs($viewer)->getJson(activityLogUrl($target).'?search='.str_repeat('a', 121))
        ->assertJsonValidationErrorFor('search');
});

it('404s for an unknown username', function () {
    actingAs(securityTestUser(['users.view_analytics', 'admin.logs']))
        ->getJson('/api/user-activity/users/nope/activity-log')
        ->assertNotFound();
});
