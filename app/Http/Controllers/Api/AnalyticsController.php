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
            ->with(['visitor' => function ($query) {
                $query->select('id', 'name', 'username')
                    ->with('media');
            }])
            ->orderByDesc('visit_count')
            ->limit(10)
            ->get()
            ->map(function ($visit) {
                $visitor = $visit->visitor;
                if ($visitor) {
                    $visitorData = [
                        'id' => $visitor->id,
                        'name' => $visitor->name,
                        'username' => $visitor->username,
                        'profile_image' => $visitor->hasMedia('profile_image')
                            ? $visitor->getMediaUrls('profile_image')
                            : null,
                    ];
                } else {
                    $visitorData = null;
                }

                return [
                    'visitor' => $visitorData,
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
        $links = $model->links;

        $dateFilter = null;
        if ($request->has('start_date') && $request->has('end_date')) {
            $dateFilter = ['start' => $request->start_date, 'end' => $request->end_date];
        } elseif ($request->has('days')) {
            $dateFilter = ['days' => $request->days];
        } else {
            $dateFilter = ['days' => 7]; // Default to last 7 days
        }

        // Get clicks by link_label (new tracking method)
        $clicksQuery = \App\Models\Click::query()
            ->where('clickable_type', get_class($model))
            ->where('clickable_id', $model->id);

        if (isset($dateFilter['start']) && isset($dateFilter['end'])) {
            $clicksQuery->inDateRange($dateFilter['start'], $dateFilter['end']);
        } else {
            $clicksQuery->lastDays($dateFilter['days']);
        }

        $clicksByLabel = $clicksQuery
            ->selectRaw('link_label, COUNT(*) as click_count')
            ->whereNotNull('link_label')
            ->groupBy('link_label')
            ->get()
            ->pluck('click_count', 'link_label');

        // Map links with their click counts
        $linksWithClicks = $links->map(function ($link) use ($clicksByLabel) {
            $label = $link->label;
            $clicks = $clicksByLabel->get($label, 0);

            return [
                'link_id' => $link->id,
                'label' => $label,
                'url' => $link->url,
                'clicks' => $clicks,
            ];
        });

        // Add Email and WhatsApp clicks (non-Link items)
        $contactLabels = ['Email', 'WhatsApp'];
        foreach ($clicksByLabel as $label => $count) {
            // Check if this label is Email, WhatsApp, or starts with "WhatsApp " (custom labels)
            $isEmailOrWhatsApp = in_array($label, $contactLabels) || str_starts_with($label, 'WhatsApp ');

            if ($isEmailOrWhatsApp) {
                // Check if it's not already in links (from Link model)
                $existsInLinks = $links->contains(fn ($link) => $link->label === $label);

                if (! $existsInLinks) {
                    // Determine URL based on label
                    $url = null;
                    if ($label === 'Email' && $model->email) {
                        $url = "mailto:{$model->email}";
                    } elseif (str_starts_with($label, 'WhatsApp')) {
                        // For WhatsApp, we don't have the phone number in this context
                        $url = null;
                    }

                    $linksWithClicks->push([
                        'link_id' => null,
                        'label' => $label,
                        'url' => $url,
                        'clicks' => $count,
                    ]);
                }
            }
        }

        $linksWithClicks = $linksWithClicks->sortByDesc('clicks')->values();

        $totalClicks = $clicksByLabel->sum();

        // Get top clickers (for authenticated clicks)
        $topClickersQuery = \App\Models\Click::query()
            ->where('clickable_type', get_class($model))
            ->where('clickable_id', $model->id);

        if (isset($dateFilter['start']) && isset($dateFilter['end'])) {
            $topClickersQuery->inDateRange($dateFilter['start'], $dateFilter['end']);
        } else {
            $topClickersQuery->lastDays($dateFilter['days']);
        }

        $topClickers = $topClickersQuery
            ->authenticated()
            ->select('clicker_id', DB::raw('COUNT(*) as click_count'))
            ->groupBy('clicker_id')
            ->with(['clicker' => function ($query) {
                $query->select('id', 'name', 'username')
                    ->with('media');
            }])
            ->orderByDesc('click_count')
            ->limit(10)
            ->get()
            ->map(function ($click) use ($model, $dateFilter) {
                $clicker = $click->clicker;
                if ($clicker) {
                    $clickerData = [
                        'id' => $clicker->id,
                        'name' => $clicker->name,
                        'username' => $clicker->username,
                        'profile_image' => $clicker->hasMedia('profile_image')
                            ? $clicker->getMediaUrls('profile_image')
                            : null,
                    ];
                } else {
                    $clickerData = null;
                }

                // Get clicked links for this clicker
                $clickedLinksQuery = \App\Models\Click::query()
                    ->where('clickable_type', get_class($model))
                    ->where('clickable_id', $model->id)
                    ->where('clicker_id', $click->clicker_id)
                    ->whereNotNull('link_label');

                if (isset($dateFilter['start']) && isset($dateFilter['end'])) {
                    $clickedLinksQuery->inDateRange($dateFilter['start'], $dateFilter['end']);
                } else {
                    $clickedLinksQuery->lastDays($dateFilter['days']);
                }

                $clickedLinks = $clickedLinksQuery
                    ->select('link_label', DB::raw('COUNT(*) as clicks'))
                    ->groupBy('link_label')
                    ->orderByDesc('clicks')
                    ->get()
                    ->map(function ($linkClick) {
                        return [
                            'label' => $linkClick->link_label,
                            'clicks' => $linkClick->clicks,
                        ];
                    });

                return [
                    'clicker' => $clickerData,
                    'click_count' => $click->click_count,
                    'clicked_links' => $clickedLinks,
                ];
            });

        return response()->json([
            'data' => [
                'summary' => [
                    'total_clicks' => $totalClicks,
                    'total_links' => $linksWithClicks->count(),
                ],
                'links' => $linksWithClicks,
                'top_clickers' => $topClickers,
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
