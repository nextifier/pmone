<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserImpersonationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

function makeMaster(): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('master');

    return $user;
}

function makeWithRole(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole($role);

    return $user;
}

// Impersonation depends on the session, which the stateless test HTTP stack does
// not start for /api routes; drive the controller directly with a real session.
function sessionRequest(User $actor, array $session = []): Request
{
    $request = Request::create('/', 'POST');
    $store = app('session')->driver();
    foreach ($session as $key => $value) {
        $store->put($key, $value);
    }
    $request->setLaravelSession($store);
    $request->setUserResolver(fn () => $actor);

    return $request;
}

// ─── Authorization matrix (HTTP, aborts before any session access) ───

it('forbids an admin from impersonating even with user permissions', function () {
    $admin = makeWithRole('admin');
    $target = makeWithRole('user');

    actingAs($admin)->postJson("/api/users/{$target->username}/impersonate")->assertForbidden();
});

it('cannot impersonate yourself', function () {
    $master = makeMaster();

    actingAs($master)->postJson("/api/users/{$master->username}/impersonate")->assertForbidden();
});

it('cannot impersonate another master', function () {
    $master = makeMaster();
    $otherMaster = makeMaster();

    actingAs($master)->postJson("/api/users/{$otherMaster->username}/impersonate")->assertForbidden();
});

// ─── Session-dependent behaviour (direct controller invocation) ───

it('lets a master impersonate a normal user, silently', function () {
    Notification::fake();
    $master = makeMaster();
    $target = makeWithRole('user');
    $activityBefore = Activity::query()->count();

    $request = sessionRequest($master);
    $response = app(UserImpersonationController::class)->start($request, $target);

    expect($response->getData(true)['data']['id'])->toBe($target->id)
        ->and($request->session()->get('impersonator_id'))->toBe($master->id)
        ->and(Auth::guard('web')->id())->toBe($target->id)
        ->and(Activity::query()->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('cannot nest impersonation', function () {
    $master = makeMaster();
    $target = makeWithRole('user');
    $request = sessionRequest($master, ['impersonator_id' => 999]);

    try {
        app(UserImpersonationController::class)->start($request, $target);
        $this->fail('Expected a 409 HttpException.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});

it('leaves impersonation and restores the original account, silently', function () {
    Notification::fake();
    $master = makeMaster();
    $target = makeWithRole('user');
    $activityBefore = Activity::query()->count();

    $request = sessionRequest($target, ['impersonator_id' => $master->id]);
    $response = app(UserImpersonationController::class)->leave($request);

    expect($response->getData(true)['data']['id'])->toBe($master->id)
        ->and($request->session()->has('impersonator_id'))->toBeFalse()
        ->and(Auth::guard('web')->id())->toBe($master->id)
        ->and(Activity::query()->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('returns 400 when leaving without an active impersonation', function () {
    $master = makeMaster();
    $request = sessionRequest($master);

    try {
        app(UserImpersonationController::class)->leave($request);
        $this->fail('Expected a 400 HttpException.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(400);
    }
});

it('exposes the impersonation flag on the profile endpoint', function () {
    $master = makeMaster();
    $target = makeWithRole('user');

    $request = sessionRequest($target, ['impersonator_id' => $master->id]);
    $response = app(UserController::class)->profile($request);
    $payload = $response->getData(true);

    expect($payload['impersonating'])->toBeTrue()
        ->and($payload['impersonator']['id'])->toBe($master->id);
});
