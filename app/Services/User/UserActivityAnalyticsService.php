<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\UserPageView;
use App\Support\UserAgentParser;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aggregates admin-app usage (presence + page views) into an analytics payload
 * for the internal user-activity dashboard. Computed on-demand: the page-view
 * window is loaded once and grouped in PHP so the queries stay portable across
 * PostgreSQL (prod) and the SQLite test database.
 */
class UserActivityAnalyticsService
{
    /**
     * Rolling window (days) that the trend / peak-hour / top-page aggregates
     * cover. Also the definition of a "monthly active" user.
     */
    private const WINDOW_DAYS = 30;

    /**
     * A user is "online" when seen within this many minutes (mirrors
     * User::isOnline() and UserSecurityController::stats()).
     */
    private const ONLINE_MINUTES = 5;

    /**
     * Lightweight KPI block for the summary strip / header cards.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->buildSummary($this->pageViews());
    }

    /**
     * Full analytics payload for the detail dashboard.
     *
     * @return array<string, mixed>
     */
    public function detail(): array
    {
        $views = $this->pageViews();

        return [
            'summary' => $this->buildSummary($views),
            'online_users' => $this->onlineUsers(),
            'activity_trend' => $this->activityTrend($views),
            'peak_hours' => $this->peakHours($views),
            'top_pages' => $this->topPages($views),
            'by_role' => $this->byRole($views),
            'devices' => $this->devices(),
            'most_active_users' => $this->mostActiveUsers($views),
        ];
    }

    /**
     * Every page view inside the rolling window, loaded once and reused.
     *
     * @return Collection<int, UserPageView>
     */
    private function pageViews(): Collection
    {
        return UserPageView::query()
            ->where('visited_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->get(['user_id', 'path', 'title', 'visited_at']);
    }

    /**
     * @param  Collection<int, UserPageView>  $views
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $views): array
    {
        $startOfToday = now()->startOfDay();
        $weekStart = now()->subDays(7);

        $todayViews = $views->filter(fn (UserPageView $v): bool => $v->visited_at->gte($startOfToday));
        $activeToday = $todayViews->pluck('user_id')->unique()->count();
        $pageViewsToday = $todayViews->count();

        return [
            'online_now' => User::query()->where('last_seen', '>', now()->subMinutes(self::ONLINE_MINUTES))->count(),
            'active_today' => $activeToday,
            'active_week' => $views->filter(fn (UserPageView $v): bool => $v->visited_at->gte($weekStart))->pluck('user_id')->unique()->count(),
            'active_month' => $views->pluck('user_id')->unique()->count(),
            'page_views_today' => $pageViewsToday,
            'avg_pages_per_active_user' => round($pageViewsToday / max($activeToday, 1), 1),
        ];
    }

    /**
     * Who is online right now, with the page they are viewing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function onlineUsers(): array
    {
        return User::query()
            ->where('last_seen', '>', now()->subMinutes(self::ONLINE_MINUTES))
            ->with(['roles:id,name', 'media'])
            ->orderByDesc('last_seen')
            ->limit(50)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'profile_image' => $user->getMediaUrls('profile_image'),
                'role' => $user->roles->first()?->name,
                'last_seen' => $user->last_seen?->toISOString(),
                'current_page' => $user->last_page ? [
                    'path' => $user->last_page,
                    'title' => $user->last_page_title,
                ] : null,
            ])
            ->all();
    }

    /**
     * Daily page views + distinct active users across the window. Empty days are
     * filled with zeroes so the x-axis stays continuous.
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<int, array<string, mixed>>
     */
    private function activityTrend(Collection $views): array
    {
        $byDate = $views->groupBy(fn (UserPageView $v): string => $v->visited_at->format('Y-m-d'));

        $period = CarbonPeriod::create(now()->subDays(self::WINDOW_DAYS - 1)->startOfDay(), now()->startOfDay());

        $rows = [];
        foreach ($period as $day) {
            $group = $byDate->get($day->format('Y-m-d'), collect());
            $rows[] = [
                'date' => $day->format('Y-m-d'),
                'page_views' => $group->count(),
                'active_users' => $group->pluck('user_id')->unique()->count(),
            ];
        }

        return $rows;
    }

    /**
     * Page views bucketed by hour-of-day across the window. Always 24 rows.
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<int, array<string, mixed>>
     */
    private function peakHours(Collection $views): array
    {
        $counts = $views->groupBy(fn (UserPageView $v): int => (int) $v->visited_at->format('G'))
            ->map(fn (Collection $group): int => $group->count());

        $rows = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $rows[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'count' => (int) ($counts[$hour] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * The 10 most-visited paths across the window.
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<int, array<string, mixed>>
     */
    private function topPages(Collection $views): array
    {
        return $views
            ->groupBy('path')
            ->map(function (Collection $group, string $path): array {
                $title = $group->firstWhere(fn (UserPageView $v): bool => $v->title !== null && $v->title !== '')?->title;

                return [
                    'path' => $path,
                    'title' => $title,
                    'views' => $group->count(),
                    'users' => $group->pluck('user_id')->unique()->count(),
                ];
            })
            ->sortByDesc('views')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Active users in the window broken down by role. Counted through the
     * model_has_roles pivot directly - Spatie's Role::users() relation is
     * guard-dependent and cannot be used with withCount() (mirrors
     * UserSecurityController::stats()).
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<int, array<string, mixed>>
     */
    private function byRole(Collection $views): array
    {
        $activeIds = $views->pluck('user_id')->unique();

        if ($activeIds->isEmpty()) {
            return [];
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.model_id', $activeIds->all())
            ->select('roles.name as role', DB::raw('count(distinct model_has_roles.model_id) as aggregate'))
            ->groupBy('roles.name')
            ->get()
            ->map(fn (object $row): array => [
                'role' => $row->role,
                'count' => (int) $row->aggregate,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Device-type + browser split of the currently stored sessions. A snapshot
     * of active sessions, not historical page views.
     *
     * @return array{device_types: array<int, array<string, mixed>>, browsers: array<int, array<string, mixed>>}
     */
    private function devices(): array
    {
        $agents = DB::table('sessions')->whereNotNull('user_id')->pluck('user_agent');

        $deviceTypes = [];
        $browsers = [];

        foreach ($agents as $agent) {
            $parsed = UserAgentParser::parse($agent);
            $deviceLabel = Str::title($parsed['device_type']);
            $deviceTypes[$deviceLabel] = ($deviceTypes[$deviceLabel] ?? 0) + 1;
            $browsers[$parsed['browser']] = ($browsers[$parsed['browser']] ?? 0) + 1;
        }

        return [
            'device_types' => $this->labelledCounts($deviceTypes),
            'browsers' => $this->labelledCounts($browsers),
        ];
    }

    /**
     * The 10 users with the most page views in the window.
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<int, array<string, mixed>>
     */
    private function mostActiveUsers(Collection $views): array
    {
        $counts = $views->groupBy('user_id')
            ->map(fn (Collection $group): int => $group->count())
            ->sortDesc()
            ->take(10);

        if ($counts->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $counts->keys()->all())
            ->with('media')
            ->get()
            ->keyBy('id');

        return $counts
            ->map(function (int $count, int $userId) use ($users): ?array {
                $user = $users->get($userId);
                if (! $user) {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'profile_image' => $user->getMediaUrls('profile_image'),
                    'views' => $count,
                    'last_seen' => $user->last_seen?->toISOString(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function labelledCounts(array $counts): array
    {
        arsort($counts);

        $rows = [];
        foreach ($counts as $label => $count) {
            $rows[] = ['label' => $label, 'count' => $count];
        }

        return $rows;
    }
}
