<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Post;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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
            ->whereIn('orders.status', ['submitted', 'confirmed'])
            ->groupBy('brand_event.event_id', 'orders.status')
            ->select(
                'brand_event.event_id',
                'orders.status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(orders.total) as total_sum')
            )
            ->get();

        $orderStatsMap = [];
        foreach ($orderStats as $stat) {
            $orderStatsMap[$stat->event_id][$stat->status] = [
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
                    'gross_area' => (float) ($event->gross_area ?? 0),
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

        // --- My Projects - all projects where user is member ---
        $myProjects = $user->projects()
            ->active()
            ->orderBy('order_column')
            ->with(['media', 'members.media'])
            ->withCount('members')
            ->get()
            ->map(function ($project) {
                $recentEvents = $project->events()
                    ->with('media')
                    ->orderByDesc('start_date')
                    ->limit(3)
                    ->get()
                    ->map(fn (Event $event) => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'slug' => $event->slug,
                        'poster_image' => $event->poster_image,
                    ]);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'username' => $project->username,
                    'profile_image' => $project->profile_image,
                    'members_count' => $project->members_count,
                    'members' => $project->members->take(4)->map(fn ($member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'profile_image' => $member->relationLoaded('media') && $member->media->firstWhere('collection_name', 'profile_image')
                            ? $member->getMediaUrls('profile_image')
                            : null,
                    ]),
                    'recent_events' => $recentEvents,
                ];
            })
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

        // Post counts by status
        $postsQuery = Post::where('created_by', $user->id);
        $totalPosts = (clone $postsQuery)->count();
        $publishedPosts = (clone $postsQuery)->where('status', 'published')->count();
        $draftPosts = (clone $postsQuery)->where('status', 'draft')->count();

        // Total views across all writer's posts (last 30 days)
        $totalViews = Post::where('created_by', $user->id)
            ->withCount(['visits' => function ($query) use ($thirtyDaysAgo) {
                $query->where('visited_at', '>=', $thirtyDaysAgo);
            }])
            ->get()
            ->sum('visits_count');

        // Recent posts - last 5
        $recentPosts = Post::where('created_by', $user->id)
            ->with([
                'tags:id,name,slug,type',
                'media' => fn ($q) => $q->where('collection_name', 'featured_image'),
            ])
            ->withCount('visits')
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'visits_count' => $post->visits_count,
                'featured_image' => $post->getMediaUrls('featured_image'),
                'published_at' => $post->published_at?->toISOString(),
                'created_at' => $post->created_at?->toISOString(),
            ]);

        // Visits per day (last 30 days) - for chart
        $postIds = Post::where('created_by', $user->id)->pluck('id');
        $visitsData = Visit::where('visitable_type', 'App\\Models\\Post')
            ->whereIn('visitable_id', $postIds)
            ->whereBetween('visited_at', [$thirtyDaysAgo, $now->copy()->endOfDay()])
            ->select(DB::raw('DATE(visited_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $visitsPerDay = [];
        $cursor = $thirtyDaysAgo->copy();
        while ($cursor->lte($now)) {
            $dateKey = $cursor->toDateString();
            $visitsPerDay[] = [
                'date' => $dateKey,
                'count' => (int) ($visitsData[$dateKey]->count ?? 0),
            ];
            $cursor->addDay();
        }

        // Top performing posts - top 5 by views
        $topPosts = Post::where('created_by', $user->id)
            ->where('status', 'published')
            ->with([
                'media' => fn ($q) => $q->where('collection_name', 'featured_image'),
            ])
            ->withCount(['visits', 'visits as recent_visits_count' => function ($query) use ($thirtyDaysAgo) {
                $query->where('visited_at', '>=', $thirtyDaysAgo);
            }])
            ->get()
            ->filter(fn ($post) => $post->visits_count > 0)
            ->sortByDesc('visits_count')
            ->take(5)
            ->values()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'visits_count' => $post->visits_count,
                'recent_visits_count' => $post->recent_visits_count,
                'featured_image' => $post->getMediaUrls('featured_image'),
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
