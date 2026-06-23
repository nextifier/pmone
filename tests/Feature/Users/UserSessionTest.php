<?php

use App\Http\Controllers\Api\UserSecurityController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

function insertSession(string $id, int $userId, ?int $lastActivity = null): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/126.0 Safari/537.36',
        'payload' => base64_encode('test'),
        'last_activity' => $lastActivity ?? now()->getTimestamp(),
    ]);
}

it('lists a users sessions for an admin with permission', function () {
    $admin = securityTestUser(['users.manage_sessions']);
    $target = User::factory()->create();
    insertSession('sess-1', $target->id);
    insertSession('sess-2', $target->id);

    actingAs($admin)->getJson("/api/users/{$target->username}/sessions")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.device.browser', 'Chrome');
});

it('forbids listing sessions without the manage_sessions permission', function () {
    $admin = securityTestUser();
    $target = User::factory()->create();

    actingAs($admin)->getJson("/api/users/{$target->username}/sessions")->assertForbidden();
});

it('revokes a single session scoped to the target user', function () {
    $admin = securityTestUser(['users.manage_sessions']);
    $target = User::factory()->create();
    $other = User::factory()->create();
    insertSession('keep', $target->id);
    insertSession('kill', $target->id);
    insertSession('other-user', $other->id);

    actingAs($admin)->deleteJson("/api/users/{$target->username}/sessions/kill")->assertOk();

    expect(DB::table('sessions')->where('id', 'kill')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'keep')->exists())->toBeTrue();
});

it('cannot revoke another users session by guessing the id', function () {
    $admin = securityTestUser(['users.manage_sessions']);
    $target = User::factory()->create();
    $other = User::factory()->create();
    insertSession('other-session', $other->id);

    // Pass the other user's session id under the target user's route.
    actingAs($admin)->deleteJson("/api/users/{$target->username}/sessions/other-session")->assertOk();

    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeTrue();
});

it('clears all sessions and tokens for another user', function () {
    $admin = securityTestUser(['users.manage_sessions']);
    $target = User::factory()->create();
    insertSession('a', $target->id);
    insertSession('b', $target->id);
    $target->createToken('mobile');

    actingAs($admin)->deleteJson("/api/users/{$target->username}/sessions")->assertOk();

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0)
        ->and($target->tokens()->count())->toBe(0);
});

it('does not delete the acting admin own current session on clear-all', function () {
    // Driven through the controller with a deterministic session id, since the
    // test HTTP client does not carry a stable session id across requests.
    $admin = securityTestUser(['users.manage_sessions']);
    $currentId = Str::random(40); // Store::isValidId requires 40 alnum chars
    insertSession($currentId, $admin->id);
    insertSession('stale', $admin->id);

    $request = Request::create("/api/users/{$admin->username}/sessions", 'DELETE');
    $session = app('session')->driver();
    $session->setId($currentId);
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $admin);

    app(UserSecurityController::class)->clearAllSessions($request, $admin);

    expect(DB::table('sessions')->where('id', $currentId)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'stale')->exists())->toBeFalse();
});

it('revokes a single API token scoped to the user', function () {
    $admin = securityTestUser(['users.manage_sessions']);
    $target = User::factory()->create();
    $token = $target->createToken('mobile')->accessToken;

    actingAs($admin)->deleteJson("/api/users/{$target->username}/tokens/{$token->id}")->assertOk();

    expect($target->tokens()->count())->toBe(0);
});

it('writes an audit log when clearing all sessions', function () {
    $admin = securityTestUser(['users.manage_sessions']);
    $target = User::factory()->create();
    insertSession('x', $target->id);

    actingAs($admin)->deleteJson("/api/users/{$target->username}/sessions")->assertOk();

    expect(
        Activity::query()
            ->where('event', 'sessions_cleared')->where('subject_id', $target->id)->exists()
    )->toBeTrue();
});

it('bulk force-logout skips the acting admin and clears others', function () {
    $admin = securityTestUser(['users.manage_sessions']);
    $t1 = User::factory()->create();
    $t2 = User::factory()->create();
    insertSession('admin-sess', $admin->id);
    insertSession('t1-sess', $t1->id);
    insertSession('t2-sess', $t2->id);

    actingAs($admin)->postJson('/api/users/bulk/force-logout', [
        'ids' => [$admin->id, $t1->id, $t2->id],
    ])->assertOk()->assertJsonPath('count', 2);

    expect(DB::table('sessions')->where('id', 'admin-sess')->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $t1->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $t2->id)->count())->toBe(0);
});
