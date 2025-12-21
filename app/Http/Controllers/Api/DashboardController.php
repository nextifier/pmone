<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Click;
use App\Models\ContactFormSubmission;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated user.
     */
    public function stats(): JsonResponse
    {
        $user = auth()->user();

        // Get user's project IDs for filtering
        $projectIds = $user->projects()->pluck('projects.id')->toArray();

        // Date ranges
        $now = now();
        $currentStart = $now->copy()->subDays(6)->startOfDay();
        $currentEnd = $now->copy()->endOfDay();
        $previousStart = $now->copy()->subDays(13)->startOfDay();
        $previousEnd = $now->copy()->subDays(7)->endOfDay();

        // Visits statistics (user profile visits)
        $visitsCurrent = $user->visits()
            ->whereBetween('visited_at', [$currentStart, $currentEnd])
            ->count();

        $visitsPrevious = $user->visits()
            ->whereBetween('visited_at', [$previousStart, $previousEnd])
            ->count();

        // Clicks statistics (on user's links)
        $clicksCurrent = Click::query()
            ->where(function ($query) use ($user, $projectIds) {
                $query->where(function ($q) use ($user) {
                    $q->where('clickable_type', User::class)
                        ->where('clickable_id', $user->id);
                });
                if (! empty($projectIds)) {
                    $query->orWhere(function ($q) use ($projectIds) {
                        $q->where('clickable_type', 'App\\Models\\Project')
                            ->whereIn('clickable_id', $projectIds);
                    });
                }
            })
            ->whereBetween('clicked_at', [$currentStart, $currentEnd])
            ->count();

        $clicksPrevious = Click::query()
            ->where(function ($query) use ($user, $projectIds) {
                $query->where(function ($q) use ($user) {
                    $q->where('clickable_type', User::class)
                        ->where('clickable_id', $user->id);
                });
                if (! empty($projectIds)) {
                    $query->orWhere(function ($q) use ($projectIds) {
                        $q->where('clickable_type', 'App\\Models\\Project')
                            ->whereIn('clickable_id', $projectIds);
                    });
                }
            })
            ->whereBetween('clicked_at', [$previousStart, $previousEnd])
            ->count();

        // Unread inbox count (contact form submissions with "new" status)
        $unreadInboxCount = 0;
        if (! empty($projectIds) && $user->hasPermissionTo('contact_forms.read')) {
            $unreadInboxCount = ContactFormSubmission::query()
                ->whereIn('project_id', $projectIds)
                ->new()
                ->count();
        }

        // Total active short links
        $totalLinks = ShortLink::query()
            ->where('user_id', $user->id)
            ->active()
            ->count();

        // Visits chart data (last 14 days)
        $visitsChartData = $this->getVisitsChartData($user, 14);

        // Recent visits (last 5)
        $recentVisits = $user->visits()
            ->with(['visitor' => function ($query) {
                $query->select('id', 'name', 'username')
                    ->with('media');
            }])
            ->orderByDesc('visited_at')
            ->limit(5)
            ->get()
            ->map(function ($visit) {
                $visitor = $visit->visitor;

                return [
                    'id' => $visit->id,
                    'visitor' => $visitor ? [
                        'id' => $visitor->id,
                        'name' => $visitor->name,
                        'username' => $visitor->username,
                        'profile_image' => $visitor->hasMedia('profile_image')
                            ? $visitor->getMediaUrls('profile_image')
                            : null,
                    ] : null,
                    'is_anonymous' => $visitor === null,
                    'visited_at' => $visit->visited_at->toISOString(),
                    'visited_at_human' => $visit->visited_at->diffForHumans(),
                ];
            });

        // Recent clicks (last 5)
        $recentClicks = Click::query()
            ->where(function ($query) use ($user, $projectIds) {
                $query->where(function ($q) use ($user) {
                    $q->where('clickable_type', User::class)
                        ->where('clickable_id', $user->id);
                });
                if (! empty($projectIds)) {
                    $query->orWhere(function ($q) use ($projectIds) {
                        $q->where('clickable_type', 'App\\Models\\Project')
                            ->whereIn('clickable_id', $projectIds);
                    });
                }
            })
            ->with(['clicker' => function ($query) {
                $query->select('id', 'name', 'username')
                    ->with('media');
            }])
            ->orderByDesc('clicked_at')
            ->limit(5)
            ->get()
            ->map(function ($click) {
                $clicker = $click->clicker;

                return [
                    'id' => $click->id,
                    'link_label' => $click->link_label,
                    'clicker' => $clicker ? [
                        'id' => $clicker->id,
                        'name' => $clicker->name,
                        'username' => $clicker->username,
                        'profile_image' => $clicker->hasMedia('profile_image')
                            ? $clicker->getMediaUrls('profile_image')
                            : null,
                    ] : null,
                    'is_anonymous' => $clicker === null,
                    'clicked_at' => $click->clicked_at->toISOString(),
                    'clicked_at_human' => $click->clicked_at->diffForHumans(),
                ];
            });

        // Top performing short links (last 7 days)
        $topLinks = ShortLink::query()
            ->where('user_id', $user->id)
            ->active()
            ->withCount(['clicks' => function ($query) use ($currentStart, $currentEnd) {
                $query->whereBetween('clicked_at', [$currentStart, $currentEnd]);
            }])
            ->get()
            ->sortByDesc('clicks_count')
            ->take(5)
            ->values()
            ->map(function ($link) {
                return [
                    'id' => $link->id,
                    'slug' => $link->slug,
                    'destination_url' => $link->destination_url,
                    'og_title' => $link->og_title,
                    'clicks_count' => $link->clicks_count,
                ];
            });

        // Calculate percentage changes
        $visitsChange = $this->calculatePercentageChange($visitsPrevious, $visitsCurrent);
        $clicksChange = $this->calculatePercentageChange($clicksPrevious, $clicksCurrent);

        return response()->json([
            'data' => [
                'stats' => [
                    'visits' => [
                        'current' => $visitsCurrent,
                        'previous' => $visitsPrevious,
                        'change' => $visitsChange,
                        'trend' => $visitsChange >= 0 ? 'up' : 'down',
                    ],
                    'clicks' => [
                        'current' => $clicksCurrent,
                        'previous' => $clicksPrevious,
                        'change' => $clicksChange,
                        'trend' => $clicksChange >= 0 ? 'up' : 'down',
                    ],
                    'inbox' => [
                        'unread' => $unreadInboxCount,
                    ],
                    'links' => [
                        'total' => $totalLinks,
                    ],
                ],
                'visits_chart' => $visitsChartData,
                'recent_visits' => $recentVisits,
                'recent_clicks' => $recentClicks,
                'top_links' => $topLinks,
            ],
        ]);
    }

    /**
     * Get visits chart data for a specified number of days.
     */
    private function getVisitsChartData(User $user, int $days): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        // Get actual visit data grouped by date
        $visitsData = $user->visits()
            ->whereBetween('visited_at', [$startDate, $endDate])
            ->selectRaw('DATE(visited_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in all dates with zero counts for missing days
        $chartData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->toDateString();
            $chartData[] = [
                'date' => $dateString,
                'label' => $currentDate->format('M d'),
                'count' => $visitsData->has($dateString) ? (int) $visitsData[$dateString]->count : 0,
            ];
            $currentDate->addDay();
        }

        return $chartData;
    }

    /**
     * Calculate percentage change between two values.
     */
    private function calculatePercentageChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
