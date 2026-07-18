<?php

use App\Models\User;
use App\Models\UserPageView;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('blocks guests and users without the analytics permission', function () {
    getJson('/api/user-activity/analytics/summary')->assertUnauthorized();
    getJson('/api/user-activity/analytics')->assertUnauthorized();

    $user = User::factory()->create(['email_verified_at' => now()]);
    actingAs($user)->getJson('/api/user-activity/analytics/summary')->assertForbidden();
    actingAs($user)->getJson('/api/user-activity/analytics')->assertForbidden();
});

it('returns the summary metrics', function () {
    $viewer = analyticsViewer();

    $a = User::factory()->create(['email_verified_at' => now(), 'last_seen' => now()]);
    $b = User::factory()->create(['email_verified_at' => now(), 'last_seen' => now()]);

    UserPageView::factory()->count(3)->create(['user_id' => $a->id, 'visited_at' => now()]);
    UserPageView::factory()->count(5)->create(['user_id' => $b->id, 'visited_at' => now()]);

    $data = actingAs($viewer)->getJson('/api/user-activity/analytics/summary')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'online_now', 'active_today', 'active_week', 'active_month',
            'page_views_today', 'avg_pages_per_active_user',
        ]])
        ->json('data');

    expect($data['active_today'])->toBe(2);
    expect($data['page_views_today'])->toBe(8);
    expect($data['avg_pages_per_active_user'])->toEqual(4.0);
});

it('returns detail sections with continuous hours and days', function () {
    $viewer = analyticsViewer();

    $online = User::factory()->create([
        'email_verified_at' => now(),
        'last_seen' => now(),
        'last_page' => '/users',
        'last_page_title' => 'Users',
    ]);
    $stale = User::factory()->create(['email_verified_at' => now(), 'last_seen' => now()->subMinutes(10)]);

    UserPageView::factory()->count(4)->create(['user_id' => $online->id, 'path' => '/users', 'title' => 'Users', 'visited_at' => now()]);
    UserPageView::factory()->count(2)->create(['user_id' => $online->id, 'path' => '/dashboard', 'title' => 'Dashboard', 'visited_at' => now()]);

    $data = actingAs($viewer)->getJson('/api/user-activity/analytics')
        ->assertOk()
        ->json('data');

    expect($data['peak_hours'])->toHaveCount(24);
    expect($data['activity_trend'])->toHaveCount(30);
    expect(collect($data['activity_trend'])->last()['date'])->toBe(now()->format('Y-m-d'));

    // top_pages sorted by views desc, /users (4) ahead of /dashboard (2).
    expect($data['top_pages'][0]['path'])->toBe('/users');
    expect($data['top_pages'][0]['views'])->toBe(4);

    $onlineIds = collect($data['online_users'])->pluck('id');
    expect($onlineIds)->toContain($online->id);
    expect($onlineIds)->not->toContain($stale->id);

    $onlineRow = collect($data['online_users'])->firstWhere('id', $online->id);
    expect($onlineRow['current_page']['path'])->toBe('/users');
});

it('narrows the window to date_from/date_to and sizes the trend to the span', function () {
    $viewer = analyticsViewer();
    $user = User::factory()->create(['email_verified_at' => now()]);

    UserPageView::factory()->count(2)->create(['user_id' => $user->id, 'visited_at' => now()->subDays(10)]);
    UserPageView::factory()->count(3)->create(['user_id' => $user->id, 'visited_at' => now()->subDays(2)]);

    $from = now()->subDays(4)->toDateString();
    $to = now()->toDateString();

    $data = actingAs($viewer)
        ->getJson("/api/user-activity/analytics?date_from={$from}&date_to={$to}")
        ->assertOk()
        ->json('data');

    expect($data['activity_trend'])->toHaveCount(5);
    expect(collect($data['activity_trend'])->first()['date'])->toBe($from);
    expect(collect($data['activity_trend'])->last()['date'])->toBe($to);
    expect(collect($data['activity_trend'])->sum('page_views'))->toBe(3);
    expect($data['summary']['active_month'])->toBe(1);
});

it('rejects a range longer than 366 days', function () {
    $viewer = analyticsViewer();

    $from = now()->subDays(400)->toDateString();
    $to = now()->toDateString();

    actingAs($viewer)
        ->getJson("/api/user-activity/analytics?date_from={$from}&date_to={$to}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);
});

it('rejects a date_to before date_from', function () {
    $viewer = analyticsViewer();

    $from = now()->toDateString();
    $to = now()->subDays(3)->toDateString();

    actingAs($viewer)
        ->getJson("/api/user-activity/analytics?date_from={$from}&date_to={$to}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);
});

it('narrows the per-user payload to the requested range', function () {
    $viewer = analyticsViewer();
    $user = User::factory()->create(['email_verified_at' => now()]);

    UserPageView::factory()->count(4)->create(['user_id' => $user->id, 'visited_at' => now()->subDays(12)]);
    UserPageView::factory()->count(2)->create(['user_id' => $user->id, 'visited_at' => now()->subDay()]);

    $from = now()->subDays(3)->toDateString();
    $to = now()->toDateString();

    $data = actingAs($viewer)
        ->getJson("/api/user-activity/users/{$user->username}/analytics?date_from={$from}&date_to={$to}")
        ->assertOk()
        ->json('data');

    expect($data['summary']['page_views_30d'])->toBe(2);
    expect($data['activity_trend'])->toHaveCount(4);
});

it('blocks guests and users without the analytics permission from the per-user payload', function () {
    $target = User::factory()->create(['email_verified_at' => now()]);

    getJson("/api/user-activity/users/{$target->username}/analytics")->assertUnauthorized();

    $user = User::factory()->create(['email_verified_at' => now()]);
    actingAs($user)->getJson("/api/user-activity/users/{$target->username}/analytics")->assertForbidden();
});

it('returns the per-user analytics payload', function () {
    $viewer = analyticsViewer();

    $target = User::factory()->create([
        'email_verified_at' => now(),
        'last_seen' => now(),
        'last_page' => '/users',
        'last_page_title' => 'Users',
    ]);

    UserPageView::factory()->count(4)->create(['user_id' => $target->id, 'path' => '/users', 'title' => 'Users', 'visited_at' => now()]);
    UserPageView::factory()->count(2)->create(['user_id' => $target->id, 'path' => '/dashboard', 'title' => 'Dashboard', 'visited_at' => now()->subDays(3)]);

    $data = actingAs($viewer)->getJson("/api/user-activity/users/{$target->username}/analytics")
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'summary' => [
                'is_online', 'last_seen', 'current_page', 'page_views_today', 'page_views_30d',
                'distinct_pages_30d', 'active_days_30d', 'avg_views_per_active_day', 'busiest_day',
                'first_view_at', 'last_view_at',
            ],
            'activity_trend' => [['date', 'page_views', 'distinct_pages']],
            'peak_hours' => [['hour', 'label', 'count']],
            'top_pages' => [['path', 'title', 'views', 'last_visited_at']],
            'recent_views' => [['id', 'path', 'title', 'visited_at']],
            'devices' => ['device_types', 'browsers', 'total_sessions'],
        ]])
        ->json('data');

    expect($data['peak_hours'])->toHaveCount(24);
    expect($data['activity_trend'])->toHaveCount(30);
    expect(collect($data['activity_trend'])->last()['date'])->toBe(now()->format('Y-m-d'));

    expect($data['summary']['is_online'])->toBeTrue();
    expect($data['summary']['page_views_30d'])->toBe(6);
    expect($data['summary']['page_views_today'])->toBe(4);
    expect($data['summary']['distinct_pages_30d'])->toBe(2);
    expect($data['summary']['active_days_30d'])->toBe(2);
    expect($data['summary']['avg_views_per_active_day'])->toEqual(3.0);
    expect($data['summary']['busiest_day']['page_views'])->toBe(4);
    expect($data['summary']['current_page']['path'])->toBe('/users');

    expect($data['top_pages'][0]['path'])->toBe('/users');
    expect($data['top_pages'][0]['views'])->toBe(4);
});

it('scopes the per-user payload to the requested user', function () {
    $viewer = analyticsViewer();

    $a = User::factory()->create(['email_verified_at' => now()]);
    $b = User::factory()->create(['email_verified_at' => now()]);

    UserPageView::factory()->count(4)->create(['user_id' => $a->id, 'path' => '/users', 'visited_at' => now()]);
    UserPageView::factory()->count(7)->create(['user_id' => $b->id, 'path' => '/reports', 'visited_at' => now()]);

    $data = actingAs($viewer)->getJson("/api/user-activity/users/{$a->username}/analytics")
        ->assertOk()
        ->json('data');

    expect($data['summary']['page_views_30d'])->toBe(4);
    expect(collect($data['top_pages'])->pluck('path')->all())->toBe(['/users']);
    expect(collect($data['recent_views'])->pluck('path'))->not->toContain('/reports');
    expect(collect($data['activity_trend'])->sum('page_views'))->toBe(4);
});

it('returns an empty per-user payload for a user with no page views', function () {
    $viewer = analyticsViewer();
    $target = User::factory()->create(['email_verified_at' => now(), 'last_seen' => null]);

    $data = actingAs($viewer)->getJson("/api/user-activity/users/{$target->username}/analytics")
        ->assertOk()
        ->json('data');

    expect($data['summary']['page_views_30d'])->toBe(0);
    expect($data['summary']['active_days_30d'])->toBe(0);
    // Proves the divide-by-zero guard on the active-day average.
    expect($data['summary']['avg_views_per_active_day'])->toEqual(0.0);
    expect($data['summary']['busiest_day'])->toBeNull();
    expect($data['summary']['first_view_at'])->toBeNull();
    expect($data['summary']['is_online'])->toBeFalse();
    expect($data['top_pages'])->toBe([]);
    expect($data['recent_views'])->toBe([]);
    expect($data['peak_hours'])->toHaveCount(24);
    expect($data['activity_trend'])->toHaveCount(30);
    expect(collect($data['activity_trend'])->sum('page_views'))->toBe(0);
});

it('scopes devices to the requested user sessions', function () {
    $viewer = analyticsViewer();

    $a = User::factory()->create(['email_verified_at' => now()]);
    $b = User::factory()->create(['email_verified_at' => now()]);

    // Sessions are driven by the array driver under test, so insert rows by hand.
    insertSessionRow($a->id, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36');
    insertSessionRow($b->id, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');

    $data = actingAs($viewer)->getJson("/api/user-activity/users/{$a->username}/analytics")
        ->assertOk()
        ->json('data');

    expect($data['devices']['total_sessions'])->toBe(1);
    expect(collect($data['devices']['browsers'])->pluck('label')->all())->toBe(['Chrome']);
});

it('404s the per-user payload for an unknown username', function () {
    actingAs(analyticsViewer())->getJson('/api/user-activity/users/nope/analytics')->assertNotFound();
});

function analyticsViewer(): User
{
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->givePermissionTo('users.view_analytics');

    return $viewer;
}

function insertSessionRow(int $userId, string $userAgent): void
{
    DB::table('sessions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => $userAgent,
        'payload' => '',
        'last_activity' => time(),
    ]);
}
