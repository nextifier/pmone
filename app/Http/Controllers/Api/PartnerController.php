<?php

namespace App\Http\Controllers\Api;

use App\Exports\PartnersExport;
use App\Exports\PartnersTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PartnersImport;
use App\Jobs\BulkSoftDeletePartners;
use App\Jobs\ProcessExcelImport;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PartnerController extends Controller
{
    /**
     * List all partners.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()
            ->with(['media']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        if ($request->boolean('client_only')) {
            $partners = $query->get();
            $data = $partners->map(fn (Partner $partner) => $this->transformPartnerForTable($partner));

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $partners->count(),
                    'total' => $partners->count(),
                ],
            ]);
        }

        $partners = $query->paginate($request->input('per_page', 15));
        $partners->getCollection()->transform(fn (Partner $partner) => $this->transformPartnerForTable($partner));

        return response()->json([
            'data' => $partners->items(),
            'meta' => [
                'current_page' => $partners->currentPage(),
                'last_page' => $partners->lastPage(),
                'per_page' => $partners->perPage(),
                'total' => $partners->total(),
            ],
        ]);
    }

    /**
     * Show a single partner.
     */
    public function show(Partner $partner): JsonResponse
    {
        $partner->load(['media', 'creator', 'updater', 'partnerCategories.event']);

        $events = $partner->partnerCategories
            ->groupBy(fn ($cat) => $cat->event_id)
            ->map(function ($categories) {
                $event = $categories->first()->event;

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'categories' => $categories->pluck('name')->toArray(),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'id' => $partner->id,
                'ulid' => $partner->ulid,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'description' => $partner->description,
                'website_url' => $partner->website_url,
                'status' => $partner->status,
                'visibility' => $partner->visibility,
                'partner_logo' => $partner->partner_logo,
                'events' => $events,
                'created_at' => $partner->created_at,
                'updated_at' => $partner->updated_at,
                'created_by' => $partner->creator ? ['id' => $partner->creator->id, 'name' => $partner->creator->name] : null,
                'updated_by' => $partner->updater ? ['id' => $partner->updater->id, 'name' => $partner->updater->name] : null,
            ],
        ]);
    }

    /**
     * Create a new partner.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'website_url' => ['nullable', 'string', 'url', 'max:500'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
            'tmp_partner_logo' => ['nullable', 'string'],
        ]);

        unset($validated['tmp_partner_logo']);

        $partner = Partner::create($validated);

        $this->handleTemporaryUpload($request, $partner, 'tmp_partner_logo', 'partner_logo');

        $partner->load('media');

        return response()->json([
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'partner_logo' => $partner->partner_logo,
            ],
            'message' => 'Partner created successfully',
        ], 201);
    }

    /**
     * Update a partner.
     */
    public function update(Request $request, Partner $partner): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'website_url' => ['nullable', 'string', 'url', 'max:500'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
            'tmp_partner_logo' => ['nullable', 'string'],
            'delete_partner_logo' => ['nullable', 'boolean'],
        ]);

        unset($validated['tmp_partner_logo'], $validated['delete_partner_logo']);

        $partner->update($validated);

        $this->handleTemporaryUpload($request, $partner, 'tmp_partner_logo', 'partner_logo');

        $partner->load('media');

        return response()->json([
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'partner_logo' => $partner->partner_logo,
            ],
            'message' => 'Partner updated successfully',
        ]);
    }

    /**
     * Bulk delete partners (soft delete, queued).
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $jobId = Str::uuid()->toString();

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => count($validated['ids']),
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Preparing to delete partners...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkSoftDeletePartners::dispatch(
            $jobId,
            $validated['ids'],
            auth()->id(),
        );

        return response()->json(['job_id' => $jobId]);
    }

    /**
     * Delete a partner (soft delete).
     */
    public function destroy(Partner $partner): JsonResponse
    {
        $partner->delete();

        return response()->json(['message' => 'Partner deleted successfully']);
    }

    /**
     * List trashed partners.
     */
    public function trash(Request $request): JsonResponse
    {
        $query = Partner::onlyTrashed()
            ->with(['media', 'deleter']);

        $this->applyFilters($query, $request);

        $sortField = $request->input('sort', '-deleted_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $trashFieldMap = [
            'partner_name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
            'deleted_at' => 'deleted_at',
        ];

        if (isset($trashFieldMap[$field])) {
            $query->orderBy($trashFieldMap[$field], $direction);
        } else {
            $query->orderBy('deleted_at', 'desc');
        }

        $partners = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $partners->map(fn (Partner $partner) => [
                ...$this->transformPartnerForTable($partner),
                'deleted_at' => $partner->deleted_at?->toISOString(),
                'deleter' => $partner->deleter ? ['name' => $partner->deleter->name] : null,
            ]),
            'meta' => [
                'current_page' => $partners->currentPage(),
                'last_page' => $partners->lastPage(),
                'per_page' => $partners->perPage(),
                'total' => $partners->total(),
            ],
        ]);
    }

    /**
     * Restore a single trashed partner.
     */
    public function restore(string $id): JsonResponse
    {
        $partner = Partner::onlyTrashed()->findOrFail($id);
        $partner->restore();

        return response()->json(['message' => 'Partner restored successfully']);
    }

    /**
     * Bulk restore trashed partners.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restored = 0;
        foreach ($validated['ids'] as $id) {
            $partner = Partner::onlyTrashed()->find($id);
            if ($partner) {
                $partner->restore();
                $restored++;
            }
        }

        return response()->json([
            'message' => "{$restored} partner(s) restored successfully",
            'restored_count' => $restored,
        ]);
    }

    /**
     * Permanently delete a single trashed partner.
     */
    public function forceDestroy(string $id): JsonResponse
    {
        $partner = Partner::onlyTrashed()->findOrFail($id);
        $partner->forceDelete();

        return response()->json(['message' => 'Partner permanently deleted']);
    }

    /**
     * Bulk permanently delete trashed partners.
     */
    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deletedCount = 0;
        foreach ($validated['ids'] as $id) {
            $partner = Partner::onlyTrashed()->find($id);
            if ($partner) {
                $partner->forceDelete();
                $deletedCount++;
            }
        }

        return response()->json([
            'message' => "{$deletedCount} partner(s) permanently deleted",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Search partners (for autocomplete).
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1'],
        ]);

        $query = Partner::query()
            ->with('media')
            ->active()
            ->orderBy('name');

        $term = trim($request->input('q', ''));

        if ($term && $term !== '*') {
            $query->where('name', 'ilike', "%{$term}%");
        }

        $partners = $query
            ->limit(50)
            ->get()
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'website_url' => $partner->website_url,
                'partner_logo' => $partner->partner_logo,
            ]);

        return response()->json(['data' => $partners]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }
        if ($status = $request->input('filter_status')) {
            $filters['status'] = $status;
        }

        $sort = $request->input('sort', 'name');

        $export = new PartnersExport($filters, $sort);

        $filename = 'partners_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'model_type' => 'Partner',
                'filename' => $filename,
            ])
            ->log('Exported partners');

        return Excel::download($export, $filename);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $filename = 'partners_import_template.xlsx';

        return Excel::download(new PartnersTemplateExport, $filename);
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

        $tempFolder = $request->input('file');

        $metadataPath = "tmp/uploads/{$tempFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return response()->json([
                'message' => 'File not found',
            ], 404);
        }

        $metadata = json_decode(
            Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$tempFolder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'message' => 'File not found',
            ], 404);
        }

        $importId = Str::uuid()->toString();

        Cache::put("import:{$importId}", [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported_count' => 0,
            'percentage' => 0,
            'errors' => [],
            'error_message' => null,
        ], now()->addMinutes(30));

        ProcessExcelImport::dispatch(
            $importId,
            Storage::disk('local')->path($filePath),
            PartnersImport::class,
            $tempFolder,
        );

        return response()->json(['import_id' => $importId]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($searchTerm = $request->input('filter_search')) {
            $query->where('name', 'ilike', "%{$searchTerm}%");
        }

        if ($status = $request->input('filter_status')) {
            $statuses = is_array($status) ? $status : explode(',', $status);
            $statuses = array_filter($statuses);

            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } elseif (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            }
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', 'partner_name');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $fieldMap = [
            'partner_name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }

    /**
     * Transform partner model for table response.
     */
    private function transformPartnerForTable(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'ulid' => $partner->ulid,
            'partner_name' => $partner->name,
            'partner_slug' => $partner->slug,
            'website_url' => $partner->website_url,
            'status' => $partner->status,
            'partner_logo' => $partner->partner_logo,
            'created_at' => $partner->created_at,
        ];
    }

    /**
     * Handle temporary file upload and move to media collection.
     */
    private function handleTemporaryUpload(Request $request, Partner $partner, string $fieldName, string $collection): void
    {
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $partner->clearMediaCollection($collection);

            return;
        }

        if (! $request->has($fieldName)) {
            return;
        }

        $value = $request->input($fieldName);

        if (! $value) {
            return;
        }

        if (! Str::startsWith($value, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$value}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(
            Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $partner->clearMediaCollection($collection);

        $partner->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }
}
