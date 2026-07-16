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
     * How many of a user's latest page views the per-user timeline shows.
     */
    private const RECENT_VIEWS_LIMIT = 30;

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
     * Full analytics payload for a single user. The global-only sections
     * (online_users, by_role, most_active_users) are deliberately absent: they
     * are either constant or meaningless for one person.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $views = $this->pageViews($user);

        return [
            'summary' => $this->userSummary($user, $views),
            'activity_trend' => $this->userActivityTrend($views),
            'peak_hours' => $this->peakHours($views),
            'top_pages' => $this->userTopPages($views),
            'recent_views' => $this->recentPageViews($user),
            'devices' => $this->devices($user),
        ];
    }

    /**
     * Every page view inside the rolling window, loaded once and reused. Scoped
     * to one user when given; rides the (user_id, visited_at) composite index.
     *
     * @return Collection<int, UserPageView>
     */
    private function pageViews(?User $user = null): Collection
    {
        return UserPageView::query()
            ->where('visited_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
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
     * KPI block for a single user. The global counterpart's active_today /
     * active_week / active_month are dropped: they are always 0 or 1 here.
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<string, mixed>
     */
    private function userSummary(User $user, Collection $views): array
    {
        $byDate = $views->groupBy(fn (UserPageView $v): string => $v->visited_at->format('Y-m-d'));
        $activeDays = $byDate->count();
        $busiestDate = $byDate->sortByDesc(fn (Collection $group): int => $group->count())->keys()->first();

        return [
            'is_online' => $user->isOnline(),
            'last_seen' => $user->last_seen?->toISOString(),
            'current_page' => $user->last_page ? [
                'path' => $user->last_page,
                'title' => $user->last_page_title,
            ] : null,
            'page_views_today' => $views->filter(
                fn (UserPageView $v): bool => $v->visited_at->gte(now()->startOfDay())
            )->count(),
            'page_views_30d' => $views->count(),
            'distinct_pages_30d' => $views->pluck('path')->unique()->count(),
            'active_days_30d' => $activeDays,
            'avg_views_per_active_day' => round($views->count() / max($activeDays, 1), 1),
            'busiest_day' => $busiestDate === null ? null : [
                'date' => $busiestDate,
                'page_views' => $byDate->get($busiestDate)->count(),
            ],
            'first_view_at' => $views->min('visited_at')?->toISOString(),
            'last_view_at' => $views->max('visited_at')?->toISOString(),
        ];
    }

    /**
     * Daily page views + distinct pages for one user. Mirrors activityTrend()'s
     * zero-filled window, but counts breadth instead of active users (which is
     * always 0 or 1 for a single person).
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<int, array<string, mixed>>
     */
    private function userActivityTrend(Collection $views): array
    {
        $byDate = $views->groupBy(fn (UserPageView $v): string => $v->visited_at->format('Y-m-d'));

        $period = CarbonPeriod::create(now()->subDays(self::WINDOW_DAYS - 1)->startOfDay(), now()->startOfDay());

        $rows = [];
        foreach ($period as $day) {
            $group = $byDate->get($day->format('Y-m-d'), collect());
            $rows[] = [
                'date' => $day->format('Y-m-d'),
                'page_views' => $group->count(),
                'distinct_pages' => $group->pluck('path')->unique()->count(),
            ];
        }

        return $rows;
    }

    /**
     * The 10 paths one user visits most. Drops topPages()'s `users` count (always
     * 1 here) and adds when they last opened each page.
     *
     * @param  Collection<int, UserPageView>  $views
     * @return array<int, array<string, mixed>>
     */
    private function userTopPages(Collection $views): array
    {
        return $views
            ->groupBy('path')
            ->map(function (Collection $group, string $path): array {
                $title = $group->firstWhere(fn (UserPageView $v): bool => $v->title !== null && $v->title !== '')?->title;

                return [
                    'path' => $path,
                    'title' => $title,
                    'views' => $group->count(),
                    'last_visited_at' => $group->max('visited_at')?->toISOString(),
                ];
            })
            ->sortByDesc('views')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * The user's latest page views. Deliberately queried outside the rolling
     * window so someone idle for longer than it still gets a timeline, and
     * separately from pageViews() so the global load need not select the id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentPageViews(User $user): array
    {
        return UserPageView::query()
            ->where('user_id', $user->id)
            ->orderByDesc('visited_at')
            ->limit(self::RECENT_VIEWS_LIMIT)
            ->get(['id', 'path', 'title', 'visited_at'])
            ->map(fn (UserPageView $view): array => [
                'id' => $view->id,
                'path' => $view->path,
                'title' => $view->title,
                'visited_at' => $view->visited_at->toISOString(),
            ])
            ->all();
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
     * Device-type + browser split of the currently stored sessions, for one user
     * when given. A snapshot of active sessions, not historical page views.
     *
     * @return array{device_types: array<int, array<string, mixed>>, browsers: array<int, array<string, mixed>>, total_sessions: int}
     */
    private function devices(?User $user = null): array
    {
        $agents = DB::table('sessions')
            ->when(
                $user,
                fn ($query) => $query->where('user_id', $user->id),
                fn ($query) => $query->whereNotNull('user_id')
            )
            ->pluck('user_agent');

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
            'total_sessions' => $agents->count(),
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
