<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Post;
use App\Models\Project;
use App\Support\VisitStats;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get navigation data for header switcher (projects + events).
     */
    public function navigation(): JsonResponse
    {
        $user = auth()->user();

        $query = $user->hasRole(['master', 'admin', 'staff'])
            ? Project::query()
            : $user->projects();

        $projects = $query
            ->active()
            ->orderBy('order_column')
            ->with(['media', 'events' => fn ($q) => $q->active()->reorder()->latest('id')->with('media')])
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'username' => $project->username,
                'profile_image' => $project->profile_image,
                'events' => $project->events->map(fn ($event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'start_date' => $event->start_date?->toISOString(),
                    'end_date' => $event->end_date?->toISOString(),
                    'poster_image' => $event->poster_image,
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => $projects]);
    }

    /**
     * Get operational dashboard statistics for the authenticated user.
     */
    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $now = now();
        $today = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        // --- All Events - with time_status, sorted by proximity to today ---
        $allEvents = Event::active()
            ->with(['project:id,username', 'media'])
            ->withCount('brandEvents')
            ->withSum([
                'brandEvents as booked_area' => fn ($q) => $q->whereNotNull('booth_size'),
            ], 'booth_size')
            ->get();

        // Batch: order stats per event (eliminates N+1)
        $eventIds = $allEvents->pluck('id');
        $orderStats = Order::query()
            ->join('brand_event', 'orders.brand_event_id', '=', 'brand_event.id')
            ->whereIn('brand_event.event_id', $eventIds)
            ->whereIn('orders.operational_status', ['submitted', 'confirmed'])
            ->groupBy('brand_event.event_id', 'orders.operational_status')
            ->select(
                'brand_event.event_id',
                'orders.operational_status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(orders.total_idr) as total_sum')
            )
            ->get();

        $orderStatsMap = [];
        foreach ($orderStats as $stat) {
            $status = $stat->operational_status instanceof \BackedEnum
                ? $stat->operational_status->value
                : $stat->operational_status;
            $orderStatsMap[$stat->event_id][$status] = [
                'count' => (int) $stat->count,
                'total_sum' => (float) $stat->total_sum,
            ];
        }

        $allEvents = $allEvents
            ->map(function (Event $event) use ($today, $todayEnd, $orderStatsMap) {
                $timeStatus = 'no_date';

                if ($event->start_date) {
                    $endDate = $event->end_date ?? $event->start_date;

                    if ($endDate->lt($today)) {
                        $timeStatus = 'completed';
                    } elseif ($event->start_date->gt($todayEnd)) {
                        $timeStatus = 'upcoming';
                    } else {
                        $timeStatus = 'ongoing';
                    }
                }

                $eventOrderStats = $orderStatsMap[$event->id] ?? [];

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'date_label' => $event->date_label,
                    'start_date' => $event->start_date?->toISOString(),
                    'end_date' => $event->end_date?->toISOString(),
                    'location' => $event->location,
                    'status' => $event->status,
                    'time_status' => $timeStatus,
                    'project_username' => $event->project?->username,
                    'poster_image' => $event->poster_image,
                    'brand_events_count' => $event->brand_events_count,
                    'orders_submitted' => $eventOrderStats['submitted']['count'] ?? 0,
                    'orders_confirmed' => $eventOrderStats['confirmed']['count'] ?? 0,
                    'saleable_area' => (float) ($event->saleable_area ?? 0),
                    'booked_area' => (float) ($event->booked_area ?? 0),
                    'total_revenue' => (float) ($eventOrderStats['confirmed']['total_sum'] ?? 0),
                ];
            })
            ->sortBy(function ($event) use ($now) {
                $priority = match ($event['time_status']) {
                    'ongoing' => 0,
                    'upcoming' => 1,
                    'completed' => 2,
                    default => 3,
                };

                $refDate = $event['time_status'] === 'completed'
                    ? ($event['end_date'] ?? $event['start_date'])
                    : $event['start_date'];

                $proximity = $refDate
                    ? abs(Carbon::parse($refDate)->floatDiffInDays($now))
                    : 9999;

                return $priority * 100000 + $proximity;
            })
            ->values();

        // --- My Projects - all projects for master/admin, only member projects for everyone else ---
        $myProjectsQuery = $user->hasRole(['master', 'admin'])
            ? Project::query()
            : $user->projects();

        $myProjects = $myProjectsQuery
            ->active()
            ->orderBy('order_column')
            ->with('media')
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'username' => $project->username,
                'profile_image' => $project->profile_image,
            ])
            ->values();

        return response()->json([
            'data' => [
                'tips' => [
                    'has_password' => ! empty($user->password),
                    'has_profile_photo' => $user->getFirstMediaUrl('profile_image') !== '',
                    'has_phone' => ! empty($user->phone),
                ],
                'all_events' => $allEvents,
                'my_projects' => $myProjects,
            ],
        ]);
    }

    /**
     * Get writer-focused dashboard statistics.
     */
    public function writerStats(): JsonResponse
    {
        $user = auth()->user();
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay();

        // Post counts by status (single query with conditional aggregation)
        $postCounts = Post::where('created_by', $user->id)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) FILTER (WHERE status = 'published') as published"),
                DB::raw("COUNT(*) FILTER (WHERE status = 'draft') as draft")
            )
            ->first();
        $totalPosts = (int) $postCounts->total;
        $publishedPosts = (int) $postCounts->published;
        $draftPosts = (int) $postCounts->draft;

        // Everything below counts only this writer's own posts.
        $ownPosts = fn ($query) => $query->whereIn('visitable_id', Post::where('created_by', $user->id)->select('id'));

        // Views over the last 30 days, read from the permanent rollup so the figure
        // matches what the analytics pages report for the same window.
        $recentSeries = VisitStats::viewSeries(Post::class, $thirtyDaysAgo, $now, $ownPosts);
        $totalViews = $recentSeries['total'];
        $recentByPost = VisitStats::viewTotalsByTarget(Post::class, $thirtyDaysAgo, $now, $ownPosts);

        // Recent posts - last 5
        $recentPosts = Post::where('created_by', $user->id)
            ->with([
                'tags:id,name,slug,type',
                'media' => fn ($q) => $q->where('collection_name', 'featured_image'),
            ])
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->tap(fn ($posts) => VisitStats::foldTodayInto($posts, Post::class, ['views' => 'lifetime_views']))
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'lifetime_views' => (int) $post->lifetime_views,
                'featured_image' => $post->getMediaUrlsDetailed('featured_image'),
                'published_at' => $post->published_at?->toISOString(),
                'created_at' => $post->created_at?->toISOString(),
            ]);

        $visitsPerDay = [];
        $cursor = $thirtyDaysAgo->copy();
        while ($cursor->lte($now)) {
            $dateKey = $cursor->toDateString();
            $visitsPerDay[] = [
                'date' => $dateKey,
                'count' => $recentSeries['per_day'][$dateKey]['views'] ?? 0,
            ];
            $cursor->addDay();
        }

        // Top performing posts - top 5 by lifetime views
        $topPosts = Post::where('created_by', $user->id)
            ->where('status', 'published')
            ->where('lifetime_views', '>', 0)
            ->with([
                'media' => fn ($q) => $q->where('collection_name', 'featured_image'),
            ])
            ->orderByDesc('lifetime_views')
            ->limit(5)
            ->get()
            ->tap(fn ($posts) => VisitStats::foldTodayInto($posts, Post::class, ['views' => 'lifetime_views']))
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'lifetime_views' => (int) $post->lifetime_views,
                'recent_views_count' => (int) ($recentByPost[$post->id] ?? 0),
                'featured_image' => $post->getMediaUrlsDetailed('featured_image'),
                'published_at' => $post->published_at?->toISOString(),
            ]);

        return response()->json([
            'data' => [
                'tips' => [
                    'has_password' => ! empty($user->password),
                    'has_profile_photo' => $user->getFirstMediaUrl('profile_image') !== '',
                    'has_phone' => ! empty($user->phone),
                ],
                'stats' => [
                    'total_posts' => $totalPosts,
                    'published_posts' => $publishedPosts,
                    'draft_posts' => $draftPosts,
                    'total_views_30d' => $totalViews,
                ],
                'visits_per_day' => $visitsPerDay,
                'recent_posts' => $recentPosts,
                'top_posts' => $topPosts,
            ],
        ]);
    }
}
