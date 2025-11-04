<?php

namespace App\Http\Controllers\Api;

use App\Exports\ShortLinksExport;
use App\Exports\ShortLinksTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShortLinkRequest;
use App\Http\Requests\UpdateShortLinkRequest;
use App\Http\Resources\ShortLinkIndexResource;
use App\Http\Resources\ShortLinkResource;
use App\Imports\ShortLinksImport;
use App\Models\ShortLink;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShortLinkController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $query = ShortLink::query()->with(['user']);
        $clientOnly = $request->boolean('client_only', false);

        // Apply filters and sorting only if not client-only mode
        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        // Paginate only if not client-only mode
        if ($clientOnly) {
            $shortLinks = $query->get();

            return response()->json([
                'data' => ShortLinkIndexResource::collection($shortLinks),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $shortLinks->count(),
                    'total' => $shortLinks->count(),
                ],
            ]);
        }

        $shortLinks = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ShortLinkIndexResource::collection($shortLinks->items()),
            'meta' => [
                'current_page' => $shortLinks->currentPage(),
                'last_page' => $shortLinks->lastPage(),
                'per_page' => $shortLinks->perPage(),
                'total' => $shortLinks->total(),
            ],
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTerm = strtolower($searchTerm);
                $q->whereRaw('LOWER(slug) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(destination_url) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Status filter (active/inactive)
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

        // User filter
        if ($userId = $request->input('filter_user')) {
            $query->where('user_id', $userId);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if (in_array($field, ['slug', 'destination_url', 'is_active', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    public function show(ShortLink $shortLink): JsonResponse
    {
        $this->authorize('view', $shortLink);

        $shortLink->load(['user', 'clicks' => function ($query) {
            $query->latest('clicked_at')->limit(10);
        }]);

        return response()->json([
            'data' => new ShortLinkResource($shortLink),
        ]);
    }

    public function store(StoreShortLinkRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['user_id'] = $request->user()->id;
            $data['is_active'] = $data['is_active'] ?? true;

            $shortLink = ShortLink::create($data);
            $shortLink->load(['user']);

            return response()->json([
                'message' => 'Short link created successfully',
                'data' => new ShortLinkResource($shortLink),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Short link creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Failed to create short link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateShortLinkRequest $request, ShortLink $shortLink): JsonResponse
    {
        try {
            $data = $request->validated();
            $shortLink->update($data);
            $shortLink->load(['user']);

            return response()->json([
                'message' => 'Short link updated successfully',
                'data' => new ShortLinkResource($shortLink),
            ]);
        } catch (\Exception $e) {
            logger()->error('Short link update failed', [
                'error' => $e->getMessage(),
                'short_link_id' => $shortLink->id,
            ]);

            return response()->json([
                'message' => 'Failed to update short link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, ShortLink $shortLink): JsonResponse
    {
        $this->authorize('delete', $shortLink);

        try {
            $shortLink->delete();

            return response()->json([
                'message' => 'Short link deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('Short link deletion failed', [
                'error' => $e->getMessage(),
                'short_link_id' => $shortLink->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete short link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:short_links,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $shortLinkIds = $request->input('ids');
            $currentUser = $request->user();
            $deletedCount = 0;
            $errors = [];

            foreach ($shortLinkIds as $shortLinkId) {
                $shortLink = ShortLink::find($shortLinkId);

                if (! $shortLink) {
                    continue;
                }

                // Authorization check
                if (! $currentUser->can('delete', $shortLink)) {
                    $errors[] = "Cannot delete short link: {$shortLink->slug}";

                    continue;
                }

                $shortLink->delete();
                $deletedCount++;
            }

            $message = $deletedCount > 0
                ? "Successfully deleted {$deletedCount} short link(s)"
                : 'No short links were deleted';

            return response()->json([
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ], $deletedCount > 0 ? 200 : 400);
        } catch (\Exception $e) {
            logger()->error('Bulk short link deletion failed', [
                'error' => $e->getMessage(),
                'short_link_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to delete short links',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAnalytics(Request $request, ShortLink $shortLink): JsonResponse
    {
        $this->authorize('view', $shortLink);

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $query = $shortLink->clicks();

        // Apply date filters
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->start_date, $request->end_date);
        } elseif ($request->has('days')) {
            $query->lastDays($request->days);
        } else {
            $query->lastDays(7); // Default to last 7 days
        }

        $totalClicks = $query->count();

        // Clicks per day
        $clicksPerDay = $query->clone()
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Recent clicks
        $recentClicks = $query->clone()
            ->latest('clicked_at')
            ->limit(20)
            ->get()
            ->map(function ($click) {
                return [
                    'id' => $click->id,
                    'clicked_at' => $click->clicked_at->toISOString(),
                    'ip_address' => $click->ip_address,
                    'user_agent' => $click->user_agent,
                    'referer' => $click->referer,
                ];
            });

        return response()->json([
            'data' => [
                'summary' => [
                    'total_clicks' => $totalClicks,
                ],
                'clicks_per_day' => $clicksPerDay,
                'recent_clicks' => $recentClicks,
            ],
        ]);
    }

    // Trash Management

    public function trash(Request $request): JsonResponse
    {
        $query = ShortLink::onlyTrashed()->with(['user', 'deleter']);
        $clientOnly = $request->boolean('client_only', false);

        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        if ($clientOnly) {
            $shortLinks = $query->get();

            return response()->json([
                'data' => \App\Http\Resources\ShortLinkIndexResource::collection($shortLinks),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $shortLinks->count(),
                    'total' => $shortLinks->count(),
                ],
            ]);
        }

        $shortLinks = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => \App\Http\Resources\ShortLinkIndexResource::collection($shortLinks->items()),
            'meta' => [
                'current_page' => $shortLinks->currentPage(),
                'last_page' => $shortLinks->lastPage(),
                'per_page' => $shortLinks->perPage(),
                'total' => $shortLinks->total(),
            ],
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $shortLink = ShortLink::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $shortLink);

        $shortLink->restore();

        return response()->json([
            'message' => 'Short link restored successfully',
        ]);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $shortLinkIds = $request->input('ids');
            $currentUser = $request->user();
            $restoredCount = 0;
            $errors = [];

            foreach ($shortLinkIds as $shortLinkId) {
                $shortLink = ShortLink::onlyTrashed()->find($shortLinkId);

                if (! $shortLink) {
                    continue;
                }

                if (! $currentUser->can('delete', $shortLink)) {
                    $errors[] = "Cannot restore short link: {$shortLink->slug}";

                    continue;
                }

                $shortLink->restore();
                $restoredCount++;
            }

            $message = $restoredCount > 0
                ? "Successfully restored {$restoredCount} short link(s)"
                : 'No short links were restored';

            return response()->json([
                'message' => $message,
                'restored_count' => $restoredCount,
                'errors' => $errors,
            ], $restoredCount > 0 ? 200 : 400);
        } catch (\Exception $e) {
            logger()->error('Bulk short link restoration failed', [
                'error' => $e->getMessage(),
                'short_link_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to restore short links',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function forceDestroy(Request $request, int $id): JsonResponse
    {
        $shortLink = ShortLink::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $shortLink);

        $shortLink->forceDelete();

        return response()->json([
            'message' => 'Short link permanently deleted',
        ]);
    }

    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $shortLinkIds = $request->input('ids');
            $currentUser = $request->user();
            $deletedCount = 0;
            $errors = [];

            foreach ($shortLinkIds as $shortLinkId) {
                $shortLink = ShortLink::onlyTrashed()->find($shortLinkId);

                if (! $shortLink) {
                    continue;
                }

                if (! $currentUser->can('delete', $shortLink)) {
                    $errors[] = "Cannot delete short link: {$shortLink->slug}";

                    continue;
                }

                $shortLink->forceDelete();
                $deletedCount++;
            }

            $message = $deletedCount > 0
                ? "Successfully deleted {$deletedCount} short link(s) permanently"
                : 'No short links were deleted';

            return response()->json([
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ], $deletedCount > 0 ? 200 : 400);
        } catch (\Exception $e) {
            logger()->error('Bulk permanent short link deletion failed', [
                'error' => $e->getMessage(),
                'short_link_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to permanently delete short links',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Import/Export

    public function export(Request $request): BinaryFileResponse
    {
        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }
        if ($status = $request->input('filter_status')) {
            $filters['status'] = $status;
        }

        $sort = $request->input('sort', '-created_at');

        $export = new ShortLinksExport($filters, $sort);

        $filename = 'short_links_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new ShortLinksTemplateExport, 'short_links_import_template.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tempFolder = null;

        try {
            $tempFolder = $request->input('file');

            // Get file path from temporary storage
            $metadataPath = "tmp/uploads/{$tempFolder}/metadata.json";

            if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($metadataPath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            $metadata = json_decode(
                \Illuminate\Support\Facades\Storage::disk('local')->get($metadataPath),
                true
            );

            $filePath = "tmp/uploads/{$tempFolder}/{$metadata['original_name']}";

            if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            // Import short links
            $import = new ShortLinksImport($request->user()->id);
            Excel::import($import, \Illuminate\Support\Facades\Storage::disk('local')->path($filePath));

            // Get import results
            $failures = $import->getFailures();
            $importedCount = $import->getImportedCount();
            $errorMessages = [];

            foreach ($failures as $failure) {
                $errorMessages[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            if (count($errorMessages) > 0) {
                return response()->json([
                    'message' => 'Import completed with errors',
                    'errors' => $errorMessages,
                    'imported_count' => $importedCount,
                ], 422);
            }

            return response()->json([
                'message' => 'Short links imported successfully',
                'imported_count' => $importedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('Short link import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import short links',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            // Always clean up temporary files
            if ($tempFolder) {
                \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("tmp/uploads/{$tempFolder}");
            }
        }
    }
}
