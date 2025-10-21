<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get visits analytics
     */
    public function getVisits(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:user,project',
            'id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $model = $request->type === 'user' ? User::findOrFail($request->id) : Project::findOrFail($request->id);

        // Authorization check
        if (! $this->canViewAnalytics($request, $model)) {
            abort(403, 'Unauthorized to view analytics for this resource.');
        }

        $query = $model->visits();

        // Apply date filters
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->start_date, $request->end_date);
        } elseif ($request->has('days')) {
            $query->lastDays($request->days);
        } else {
            $query->lastDays(7); // Default to last 7 days
        }

        $totalVisits = $query->count();
        $authenticatedVisits = $query->clone()->authenticated()->count();
        $anonymousVisits = $query->clone()->anonymous()->count();

        // Visits per day
        $visitsPerDay = $query->clone()
            ->selectRaw('DATE(visited_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top visitors (for authenticated visits)
        $topVisitors = $query->clone()
            ->authenticated()
            ->select('visitor_id', DB::raw('COUNT(*) as visit_count'))
            ->groupBy('visitor_id')
            ->with('visitor:id,name,username')
            ->orderByDesc('visit_count')
            ->limit(10)
            ->get()
            ->map(function ($visit) {
                return [
                    'visitor' => $visit->visitor,
                    'visit_count' => $visit->visit_count,
                ];
            });

        return response()->json([
            'data' => [
                'summary' => [
                    'total_visits' => $totalVisits,
                    'authenticated_visits' => $authenticatedVisits,
                    'anonymous_visits' => $anonymousVisits,
                ],
                'visits_per_day' => $visitsPerDay,
                'top_visitors' => $topVisitors,
            ],
        ]);
    }

    /**
     * Get clicks analytics
     */
    public function getClicks(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:user,project',
            'id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $model = $request->type === 'user' ? User::findOrFail($request->id) : Project::findOrFail($request->id);

        // Authorization check
        if (! $this->canViewAnalytics($request, $model)) {
            abort(403, 'Unauthorized to view analytics for this resource.');
        }

        // Get all links for this entity
        $links = $model->links()->with('clicks')->get();

        $dateFilter = null;
        if ($request->has('start_date') && $request->has('end_date')) {
            $dateFilter = ['start' => $request->start_date, 'end' => $request->end_date];
        } elseif ($request->has('days')) {
            $dateFilter = ['days' => $request->days];
        } else {
            $dateFilter = ['days' => 7]; // Default to last 7 days
        }

        $linksWithClicks = $links->map(function ($link) use ($dateFilter) {
            $clicksQuery = $link->clicks();

            if (isset($dateFilter['start']) && isset($dateFilter['end'])) {
                $clicksQuery->inDateRange($dateFilter['start'], $dateFilter['end']);
            } else {
                $clicksQuery->lastDays($dateFilter['days']);
            }

            $totalClicks = $clicksQuery->count();

            return [
                'link_id' => $link->id,
                'label' => $link->label,
                'url' => $link->url,
                'clicks' => $totalClicks,
            ];
        })->sortByDesc('clicks')->values();

        $totalClicks = $linksWithClicks->sum('clicks');

        return response()->json([
            'data' => [
                'summary' => [
                    'total_clicks' => $totalClicks,
                    'total_links' => $links->count(),
                ],
                'links' => $linksWithClicks,
            ],
        ]);
    }

    /**
     * Get analytics summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:user,project',
            'id' => 'required|integer',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $model = $request->type === 'user' ? User::findOrFail($request->id) : Project::findOrFail($request->id);

        // Authorization check
        if (! $this->canViewAnalytics($request, $model)) {
            abort(403, 'Unauthorized to view analytics for this resource.');
        }

        $days = $request->get('days', 7);

        $totalVisits = $model->visits()->lastDays($days)->count();
        $links = $model->links;
        $totalClicks = $links->sum(function ($link) use ($days) {
            return $link->clicks()->lastDays($days)->count();
        });

        return response()->json([
            'data' => [
                'total_visits' => $totalVisits,
                'total_clicks' => $totalClicks,
                'total_links' => $links->count(),
                'period_days' => $days,
            ],
        ]);
    }

    /**
     * Get activity log for authenticated user visits
     */
    public function getActivityLog(Request $request): JsonResponse
    {
        $user = auth()->user();

        $visits = $user->visits()
            ->authenticated()
            ->with('visitor:id,name,username')
            ->orderByDesc('visited_at')
            ->paginate(20);

        return response()->json($visits);
    }

    /**
     * Check if user can view analytics
     */
    private function canViewAnalytics(Request $request, $model): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // User can view their own analytics
        if ($model instanceof User && $model->id === $user->id) {
            return true;
        }

        // Project members can view project analytics
        if ($model instanceof Project && $model->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Master and admin can view all analytics
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return false;
    }
}
