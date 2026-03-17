<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DateRangeHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkPageRequest;
use App\Http\Requests\UpdateLinkPageRequest;
use App\Http\Resources\LinkPageIndexResource;
use App\Http\Resources\LinkPageResource;
use App\Models\LinkPage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\ResponseCache\Facades\ResponseCache;

class LinkPageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LinkPage::class);

        $query = LinkPage::query()->with(['user'])->withCount(['items', 'visits', 'clicks']);
        $clientOnly = $request->boolean('client_only', false);

        $user = $request->user();
        if ($user && ! $user->hasRole(['master', 'admin', 'staff'])) {
            $query->where('user_id', $user->id);
        }

        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        if ($clientOnly) {
            $linkPages = $query->get();

            return response()->json([
                'data' => LinkPageIndexResource::collection($linkPages),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $linkPages->count(),
                    'total' => $linkPages->count(),
                ],
            ]);
        }

        $linkPages = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => LinkPageIndexResource::collection($linkPages->items()),
            'meta' => [
                'current_page' => $linkPages->currentPage(),
                'last_page' => $linkPages->lastPage(),
                'per_page' => $linkPages->perPage(),
                'total' => $linkPages->total(),
            ],
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTerm = strtolower($searchTerm);
                $q->whereRaw('LOWER(slug) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(title) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        if ($statuses = $request->input('filter_status')) {
            $statusArray = explode(',', $statuses);
            $query->where(function ($q) use ($statusArray) {
                foreach ($statusArray as $status) {
                    if ($status === 'active') {
                        $q->orWhere('is_active', true);
                    } elseif ($status === 'inactive') {
                        $q->orWhere('is_active', false);
                    }
                }
            });
        }

        if ($userId = $request->input('filter_user')) {
            $query->where('user_id', $userId);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if (in_array($field, ['slug', 'title', 'is_active', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    public function checkSlug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:255'],
            'exclude_id' => ['nullable', 'integer', 'exists:link_pages,id'],
        ]);

        $slug = $request->input('slug');
        $excludeId = $request->input('exclude_id');

        $query = LinkPage::withTrashed()->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $available = ! $query->exists();

        return response()->json([
            'available' => $available,
        ]);
    }

    public function show(LinkPage $linkPage): JsonResponse
    {
        $this->authorize('view', $linkPage);

        $linkPage->loadCount(['items', 'visits', 'clicks']);
        $linkPage->load([
            'user',
            'items' => function ($query) {
                $query->ordered();
            },
        ]);

        return response()->json([
            'data' => new LinkPageResource($linkPage),
        ]);
    }

    public function store(StoreLinkPageRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['user_id'] = $request->user()->id;
            $data['is_active'] = $data['is_active'] ?? true;

            $linkPage = LinkPage::create($data);

            if ($request->hasFile('cover_image')) {
                $linkPage->addMediaFromRequest('cover_image')->toMediaCollection('cover_image');
            }

            $linkPage->load(['user', 'items']);
            $linkPage->loadCount(['items', 'visits', 'clicks']);

            return response()->json([
                'message' => 'Link page created successfully',
                'data' => new LinkPageResource($linkPage),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Link page creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Failed to create link page',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateLinkPageRequest $request, LinkPage $linkPage): JsonResponse
    {
        try {
            $data = $request->validated();
            $linkPage->update($data);

            if ($request->hasFile('cover_image')) {
                $linkPage->addMediaFromRequest('cover_image')->toMediaCollection('cover_image');
            }

            if ($request->boolean('remove_cover_image', false)) {
                $linkPage->clearMediaCollection('cover_image');
            }

            $linkPage->load(['user', 'items' => fn ($q) => $q->ordered()]);
            $linkPage->loadCount(['items', 'visits', 'clicks']);

            return response()->json([
                'message' => 'Link page updated successfully',
                'data' => new LinkPageResource($linkPage),
            ]);
        } catch (\Exception $e) {
            logger()->error('Link page update failed', [
                'error' => $e->getMessage(),
                'link_page_id' => $linkPage->id,
            ]);

            return response()->json([
                'message' => 'Failed to update link page',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, LinkPage $linkPage): JsonResponse
    {
        $this->authorize('delete', $linkPage);

        try {
            $linkPage->delete();

            return response()->json([
                'message' => 'Link page deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('Link page deletion failed', [
                'error' => $e->getMessage(),
                'link_page_id' => $linkPage->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete link page',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:link_pages,id'],
            'orders.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        foreach ($validated['orders'] as $orderData) {
            $cases[] = $isPostgres ? 'WHEN id = ? THEN ?::integer' : 'WHEN id = ? THEN ?';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        DB::statement(
            "UPDATE link_pages SET order_column = CASE {$casesString} END WHERE id IN ({$idsString})",
            $params
        );

        ResponseCache::clear(['short-links']);

        return response()->json([
            'message' => 'Link page order updated successfully',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:link_pages,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $currentUser = $request->user();
            $deletedCount = 0;
            $errors = [];

            $linkPages = LinkPage::whereIn('id', $request->input('ids'))->get();

            foreach ($linkPages as $linkPage) {
                if (! $currentUser->can('delete', $linkPage)) {
                    $errors[] = "Cannot delete link page: {$linkPage->slug}";

                    continue;
                }

                $linkPage->delete();
                $deletedCount++;
            }

            $message = $deletedCount > 0
                ? "Successfully deleted {$deletedCount} link page(s)"
                : 'No link pages were deleted';

            return response()->json([
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ], $deletedCount > 0 ? 200 : 400);
        } catch (\Exception $e) {
            logger()->error('Bulk link page deletion failed', [
                'error' => $e->getMessage(),
                'link_page_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to delete link pages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LinkPage::class);

        $query = LinkPage::onlyTrashed()->with(['user', 'deleter'])->withCount(['items', 'visits', 'clicks']);
        $clientOnly = $request->boolean('client_only', false);

        $user = $request->user();
        if ($user && ! $user->hasRole(['master', 'admin', 'staff'])) {
            $query->where('user_id', $user->id);
        }

        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        if ($clientOnly) {
            $linkPages = $query->get();

            return response()->json([
                'data' => LinkPageIndexResource::collection($linkPages),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $linkPages->count(),
                    'total' => $linkPages->count(),
                ],
            ]);
        }

        $linkPages = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => LinkPageIndexResource::collection($linkPages->items()),
            'meta' => [
                'current_page' => $linkPages->currentPage(),
                'last_page' => $linkPages->lastPage(),
                'per_page' => $linkPages->perPage(),
                'total' => $linkPages->total(),
            ],
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $linkPage = LinkPage::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $linkPage);

        $linkPage->restore();

        return response()->json([
            'message' => 'Link page restored successfully',
        ]);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $this->authorize('restoreAny', LinkPage::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:link_pages,id',
        ]);

        $linkPages = LinkPage::onlyTrashed()->whereIn('id', $request->ids)->get();

        foreach ($linkPages as $linkPage) {
            $this->authorize('restore', $linkPage);
        }

        LinkPage::onlyTrashed()->whereIn('id', $request->ids)->restore();

        return response()->json([
            'message' => count($request->ids).' link page(s) restored successfully',
        ]);
    }

    public function forceDestroy(Request $request, int $id): JsonResponse
    {
        $linkPage = LinkPage::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $linkPage);

        $linkPage->forceDelete();

        return response()->json([
            'message' => 'Link page permanently deleted',
        ]);
    }

    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $this->authorize('forceDeleteAny', LinkPage::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:link_pages,id',
        ]);

        $linkPages = LinkPage::onlyTrashed()->whereIn('id', $request->ids)->get();

        foreach ($linkPages as $linkPage) {
            $this->authorize('forceDelete', $linkPage);
        }

        LinkPage::onlyTrashed()->whereIn('id', $request->ids)->forceDelete();

        return response()->json([
            'message' => count($request->ids).' link page(s) permanently deleted',
        ]);
    }

    public function getAnalytics(Request $request, LinkPage $linkPage): JsonResponse
    {
        $this->authorize('view', $linkPage);

        $request->validate([
            'period' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        // Visits query
        $visitQuery = $linkPage->visits();

        if ($request->has('period')) {
            $dateRange = DateRangeHelper::getDateRange($request->period);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];
            $visitQuery->inDateRange($startDate, $endDate);
        } elseif ($request->has('start_date') && $request->has('end_date')) {
            $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
            $visitQuery->inDateRange($startDate, $endDate);
        } elseif ($request->has('days')) {
            $startDate = now()->subDays($request->days)->startOfDay();
            $endDate = now()->endOfDay();
            $visitQuery->lastDays($request->days);
        } else {
            $startDate = now()->subDays(7)->startOfDay();
            $endDate = now()->endOfDay();
            $visitQuery->lastDays(7);
        }

        $totalVisits = $visitQuery->count();
        $authenticatedVisits = $visitQuery->clone()->authenticated()->count();
        $anonymousVisits = $visitQuery->clone()->anonymous()->count();

        // Visits per day
        $visitsData = $visitQuery->clone()
            ->selectRaw('DATE(visited_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

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

        // Top visitors (authenticated)
        $topVisitors = $visitQuery->clone()
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

        // Per-item clicks
        $items = $linkPage->items()->ordered()->with('media')->get();
        $itemClicks = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'label' => $item->label,
                'url' => $item->url,
                'poster' => $item->poster,
                'clicks_count' => $item->clicks()->count(),
            ];
        });

        $totalClicks = $itemClicks->sum('clicks_count');

        return response()->json([
            'data' => [
                'summary' => [
                    'total_visits' => $totalVisits,
                    'authenticated_visits' => $authenticatedVisits,
                    'anonymous_visits' => $anonymousVisits,
                    'total_clicks' => $totalClicks,
                    'total_items' => $items->count(),
                ],
                'visits_per_day' => $visitsPerDay,
                'top_visitors' => $topVisitors,
                'item_clicks' => $itemClicks,
            ],
        ]);
    }
}
