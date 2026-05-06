<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Click;
use App\Models\LinkPage;
use App\Models\Project;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
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
            'type' => 'required|in:user,project,linkpage,brand,brand_event',
            'id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $model = match ($request->type) {
            'user' => User::findOrFail($request->id),
            'project' => Project::findOrFail($request->id),
            'linkpage' => LinkPage::findOrFail($request->id),
            'brand' => Brand::findOrFail($request->id),
            'brand_event' => BrandEvent::with('brand')->findOrFail($request->id),
        };

        // Authorization check
        if (! $this->canViewAnalytics($request, $model)) {
            abort(403, 'Unauthorized to view analytics for this resource.');
        }

        $query = $this->buildVisitsQuery($model);

        // Determine date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->inDateRange($request->start_date, $request->end_date);
        } elseif ($request->has('days')) {
            $days = $request->days;
            $startDate = now()->subDays($days - 1)->startOfDay();
            $endDate = now()->endOfDay();
            $query->lastDays($days);
        } else {
            $startDate = now()->subDays(6)->startOfDay();
            $endDate = now()->endOfDay();
            $query->lastDays(7); // Default to last 7 days
        }

        $totalVisits = $query->count();
        $authenticatedVisits = $query->clone()->authenticated()->count();
        $anonymousVisits = $query->clone()->anonymous()->count();

        // Visits per day - get actual data
        $visitsData = $query->clone()
            ->selectRaw('DATE(visited_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in all dates in the range with zero counts
        $visitsPerDay = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->toDateString();
            $visitsPerDay->push([
                'date' => $dateString,
                'count' => $visitsData->has($dateString) ? (int) $visitsData[$dateString]->count : 0,
            ]);
            $currentDate->addDay();
        }

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

        $perEventBreakdown = $model instanceof Brand
            ? $this->buildPerEventBreakdown($model, $startDate, $endDate)
            : null;

        return response()->json([
            'data' => [
                'summary' => [
                    'total_visits' => $totalVisits,
                    'authenticated_visits' => $authenticatedVisits,
                    'anonymous_visits' => $anonymousVisits,
                ],
                'visits_per_day' => $visitsPerDay,
                'top_visitors' => $topVisitors,
                'per_event_breakdown' => $perEventBreakdown,
            ],
        ]);
    }

    /**
     * Get clicks analytics
     */
    public function getClicks(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:user,project,linkpage,brand,brand_event',
            'id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $model = match ($request->type) {
            'user' => User::findOrFail($request->id),
            'project' => Project::findOrFail($request->id),
            'linkpage' => LinkPage::findOrFail($request->id),
            'brand' => Brand::findOrFail($request->id),
            'brand_event' => BrandEvent::with('brand')->findOrFail($request->id),
        };

        // Authorization check
        if (! $this->canViewAnalytics($request, $model)) {
            abort(403, 'Unauthorized to view analytics for this resource.');
        }

        // Get all links for this entity (BrandEvent inherits from parent Brand)
        $links = $model instanceof BrandEvent ? $model->brand->links : $model->links;

        $dateFilter = null;
        if ($request->has('start_date') && $request->has('end_date')) {
            $dateFilter = ['start' => $request->start_date, 'end' => $request->end_date];
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } elseif ($request->has('days')) {
            $dateFilter = ['days' => $request->days];
            $startDate = now()->subDays($request->days - 1)->startOfDay();
            $endDate = now()->endOfDay();
        } else {
            $dateFilter = ['days' => 7]; // Default to last 7 days
            $startDate = now()->subDays(6)->startOfDay();
            $endDate = now()->endOfDay();
        }

        // Get clicks by link_label (new tracking method)
        $clicksQuery = $this->buildClicksQuery($model);

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

        // For Brand (global view), compute per-edition distribution per link label.
        $perLinkPerEvent = collect();
        if ($model instanceof Brand) {
            $perLinkPerEvent = $this->buildPerLinkPerEvent($model, $startDate, $endDate);
        }

        // Map links with their click counts
        $linksWithClicks = $links->map(function ($link) use ($clicksByLabel, $perLinkPerEvent, $model) {
            $label = $link->label;
            $clicks = $clicksByLabel->get($label, 0);

            $entry = [
                'link_id' => $link->id,
                'label' => $label,
                'url' => $link->url,
                'clicks' => $clicks,
            ];

            if ($model instanceof Brand) {
                $entry['per_event'] = $perLinkPerEvent->get($label, collect())->all();
            }

            return $entry;
        });

        // Add any tracked label not already represented as a Link entry.
        // Covers Email/WhatsApp contact buttons plus any custom labels emitted
        // by public sites (e.g. "Tickets", "Brands", "Download Visitor E-Guide").
        foreach ($clicksByLabel as $label => $count) {
            $existsInLinks = $links->contains(fn ($link) => $link->label === $label);
            if ($existsInLinks) {
                continue;
            }

            $url = null;
            if ($label === 'Email' && $model->email) {
                $url = "mailto:{$model->email}";
            }

            $entry = [
                'link_id' => null,
                'label' => $label,
                'url' => $url,
                'clicks' => $count,
            ];

            if ($model instanceof Brand) {
                $entry['per_event'] = $perLinkPerEvent->get($label, collect())->all();
            }

            $linksWithClicks->push($entry);
        }

        $linksWithClicks = $linksWithClicks->sortByDesc('clicks')->values();

        $totalClicks = $clicksByLabel->sum();

        // Get top clickers (for authenticated clicks)
        $topClickersQuery = $this->buildClicksQuery($model);

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
                $clickedLinksQuery = $this->buildClicksQuery($model)
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

        $perEventBreakdown = $model instanceof Brand
            ? $this->buildPerEventBreakdown($model, $startDate, $endDate)
            : null;

        return response()->json([
            'data' => [
                'summary' => [
                    'total_clicks' => $totalClicks,
                    'total_links' => $linksWithClicks->count(),
                ],
                'links' => $linksWithClicks,
                'top_clickers' => $topClickers,
                'per_event_breakdown' => $perEventBreakdown,
            ],
        ]);
    }

    /**
     * Get analytics summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:user,project,linkpage,brand,brand_event',
            'id' => 'required|integer',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $model = match ($request->type) {
            'user' => User::findOrFail($request->id),
            'project' => Project::findOrFail($request->id),
            'linkpage' => LinkPage::findOrFail($request->id),
            'brand' => Brand::findOrFail($request->id),
            'brand_event' => BrandEvent::with('brand')->findOrFail($request->id),
        };

        // Authorization check
        if (! $this->canViewAnalytics($request, $model)) {
            abort(403, 'Unauthorized to view analytics for this resource.');
        }

        $days = $request->get('days', 7);

        $totalVisits = $this->buildVisitsQuery($model)->lastDays($days)->count();
        $totalLinks = $model instanceof BrandEvent
            ? $model->brand->links()->count()
            : $model->links()->count();
        $totalClicks = $this->buildClicksQuery($model)->lastDays($days)->count();

        return response()->json([
            'data' => [
                'total_visits' => $totalVisits,
                'total_clicks' => $totalClicks,
                'total_links' => $totalLinks,
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
     * Build per-edition aggregated breakdown for a Brand within a date range.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPerEventBreakdown(Brand $brand, Carbon $startDate, Carbon $endDate): array
    {
        $brandEvents = $brand->brandEvents()->with('event.project')->get();
        $brandEventIds = $brandEvents->pluck('id');

        if ($brandEventIds->isEmpty()) {
            return [];
        }

        $visitsByEvent = Visit::query()
            ->where('visitable_type', BrandEvent::class)
            ->whereIn('visitable_id', $brandEventIds)
            ->where('visited_at', '>=', $startDate)
            ->where('visited_at', '<=', $endDate)
            ->select('visitable_id', DB::raw('COUNT(*) as count'))
            ->groupBy('visitable_id')
            ->pluck('count', 'visitable_id');

        $clicksByEvent = Click::query()
            ->where('clickable_type', BrandEvent::class)
            ->whereIn('clickable_id', $brandEventIds)
            ->where('clicked_at', '>=', $startDate)
            ->where('clicked_at', '<=', $endDate)
            ->select('clickable_id', DB::raw('COUNT(*) as count'))
            ->groupBy('clickable_id')
            ->pluck('count', 'clickable_id');

        return $brandEvents->map(function ($be) use ($visitsByEvent, $clicksByEvent) {
            $event = $be->event;

            return [
                'brand_event_id' => $be->id,
                'event' => $event ? [
                    'id' => $event->id,
                    'slug' => $event->slug,
                    'title' => $event->title,
                    'edition_number' => $event->edition_number,
                    'project_username' => $event->project?->username,
                ] : null,
                'visits' => (int) ($visitsByEvent[$be->id] ?? 0),
                'clicks' => (int) ($clicksByEvent[$be->id] ?? 0),
            ];
        })
            ->sortByDesc(fn ($entry) => $entry['visits'] + $entry['clicks'])
            ->values()
            ->all();
    }

    /**
     * Build per-link click distribution across the brand's BrandEvents.
     *
     * Returns a Collection keyed by link_label, each value an array of
     * [brand_event_id, edition_number, event_title, clicks] entries.
     */
    private function buildPerLinkPerEvent(Brand $brand, Carbon $startDate, Carbon $endDate)
    {
        $brandEvents = $brand->brandEvents()->with('event')->get()->keyBy('id');
        $brandEventIds = $brandEvents->keys();

        if ($brandEventIds->isEmpty()) {
            return collect();
        }

        $rows = Click::query()
            ->where('clickable_type', BrandEvent::class)
            ->whereIn('clickable_id', $brandEventIds)
            ->whereNotNull('link_label')
            ->where('clicked_at', '>=', $startDate)
            ->where('clicked_at', '<=', $endDate)
            ->select('link_label', 'clickable_id', DB::raw('COUNT(*) as clicks'))
            ->groupBy('link_label', 'clickable_id')
            ->get();

        return $rows->groupBy('link_label')->map(function ($items) use ($brandEvents) {
            return $items->map(function ($row) use ($brandEvents) {
                $be = $brandEvents->get($row->clickable_id);
                $event = $be?->event;

                return [
                    'brand_event_id' => (int) $row->clickable_id,
                    'edition_number' => $event?->edition_number,
                    'event_title' => $event?->title,
                    'clicks' => (int) $row->clicks,
                ];
            })
                ->sortByDesc('clicks')
                ->values();
        });
    }

    /**
     * Build base visits query for a model.
     *
     * For Brand, aggregates across all the brand's BrandEvents (global view).
     * For BrandEvent, queries that BrandEvent directly. Other models use their
     * own polymorphic visits() relationship.
     */
    private function buildVisitsQuery($model)
    {
        if ($model instanceof Brand) {
            $brandEventIds = $model->brandEvents()->pluck('id');

            return Visit::query()
                ->where('visitable_type', BrandEvent::class)
                ->whereIn('visitable_id', $brandEventIds);
        }

        return $model->visits();
    }

    /**
     * Build base clicks query for a model.
     *
     * For Brand, aggregates across all the brand's BrandEvents (global view).
     * For BrandEvent, queries that BrandEvent directly. Other models use a
     * polymorphic class+id lookup.
     */
    private function buildClicksQuery($model)
    {
        if ($model instanceof Brand) {
            $brandEventIds = $model->brandEvents()->pluck('id');

            return Click::query()
                ->where('clickable_type', BrandEvent::class)
                ->whereIn('clickable_id', $brandEventIds);
        }

        return Click::query()
            ->where('clickable_type', get_class($model))
            ->where('clickable_id', $model->id);
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

        // LinkPage owner can view their own analytics
        if ($model instanceof LinkPage && $model->user_id === $user->id) {
            return true;
        }

        // Brand members can view brand analytics
        if ($model instanceof Brand && $model->users()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // BrandEvent inherits authorization from parent Brand
        if ($model instanceof BrandEvent && $model->brand?->users()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Users with analytics.view permission can view all analytics
        if ($user->hasPermissionTo('analytics.view')) {
            return true;
        }

        return false;
    }
}
