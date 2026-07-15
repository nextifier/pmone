<?php

use App\Models\User;
use App\Models\UserPageView;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

function analyticsViewer(): User
{
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->givePermissionTo('users.view_analytics');

    return $viewer;
}
