<?php

namespace App\Http\Controllers\Api;

use App\Exports\BrandsExport;
use App\Exports\BrandsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\BrandsImport;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandController extends Controller
{
    /**
     * List brands. Staff+ see all, exhibitors see only their assigned brands.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Brand::query()
            ->with(['media', 'brandEvents.event.media', 'tags'])
            ->withCount(['brandEvents', 'users']);

        // Exhibitors only see brands assigned to them
        if (! $user->hasRole(['master', 'admin', 'staff'])) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        // Client-only mode: return all data for client-side TableData
        if ($request->boolean('client_only')) {
            $brands = $query->get();

            $data = $brands->map(fn (Brand $brand) => $this->transformBrandForTable($brand));

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $brands->count(),
                    'total' => $brands->count(),
                ],
            ]);
        }

        // Server-side pagination
        $brands = $query->paginate($request->input('per_page', 15));

        $brands->getCollection()->transform(fn (Brand $brand) => $this->transformBrandForTable($brand));

        return response()->json([
            'data' => $brands->items(),
            'meta' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
            ],
        ]);
    }

    /**
     * Show a single brand. Staff+ can view any, exhibitors only their own.
     */
    public function show(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeExhibitorAccess($request->user(), $brand);

        $brand->load(['media', 'tags']);

        return response()->json([
            'data' => [
                'id' => $brand->id,
                'ulid' => $brand->ulid,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'company_name' => $brand->company_name,
                'company_address' => $brand->company_address,
                'company_email' => $brand->company_email,
                'company_phone' => $brand->company_phone,
                'custom_fields' => $brand->custom_fields,
                'status' => $brand->status,
                'visibility' => $brand->visibility,
                'brand_logo' => $brand->brand_logo,
                'business_categories' => $brand->business_categories_list,
            ],
        ]);
    }

    /**
     * Update a brand. Staff+ can update any, exhibitors only their own.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeExhibitorAccess($request->user(), $brand);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'business_categories' => ['nullable', 'array'],
            'business_categories.*' => ['string', 'max:100'],
            'tmp_brand_logo' => ['nullable', 'string'],
            'delete_brand_logo' => ['nullable', 'boolean'],
        ]);

        $categories = $validated['business_categories'] ?? null;
        unset($validated['business_categories'], $validated['tmp_brand_logo'], $validated['delete_brand_logo']);

        $brand->update($validated);

        if ($categories !== null) {
            $brand->syncBusinessCategories($categories);
        }

        $this->handleTemporaryUpload($request, $brand, 'tmp_brand_logo', 'brand_logo');

        $brand->load('media');

        return response()->json([
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'brand_logo' => $brand->brand_logo,
            ],
            'message' => 'Brand updated successfully',
        ]);
    }

    /**
     * Delete a brand (soft delete, staff+).
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }

    /**
     * Search brands globally (staff+).
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1'],
        ]);

        $query = Brand::query()
            ->with('media')
            ->orderBy('name');

        $term = trim($request->input('q', ''));

        if ($term && $term !== '*') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('company_name', 'ilike', "%{$term}%");
            });
        }

        $brands = $query
            ->limit(50)
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'company_name' => $brand->company_name,
                'brand_logo' => $brand->brand_logo,
            ]);

        return response()->json(['data' => $brands]);
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

        $export = new BrandsExport($filters, $sort);

        $filename = 'brands_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $filename = 'brands_import_template.xlsx';

        return Excel::download(new BrandsTemplateExport, $filename);
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

            $import = new BrandsImport;
            Excel::import($import, Storage::disk('local')->path($filePath));

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
                'message' => 'Brands imported successfully',
                'imported_count' => $importedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('Brand import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import brands',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            if ($tempFolder) {
                Storage::disk('local')->deleteDirectory("tmp/uploads/{$tempFolder}");
            }
        }
    }

    private function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('company_name', 'ilike', "%{$searchTerm}%");
            });
        }

        // Status filter - support single, multiple, or comma-separated values
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
        $sortField = $request->input('sort', 'brand_name');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        // Map frontend column names to database column names
        $fieldMap = [
            'brand_name' => 'name',
            'company_name' => 'company_name',
            'status' => 'status',
            'members_count' => 'users_count',
            'created_at' => 'created_at',
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }

    /**
     * Ensure non-staff users can only access their own brands.
     */
    private function authorizeExhibitorAccess(\App\Models\User $user, Brand $brand): void
    {
        if ($user->hasRole(['master', 'admin', 'staff'])) {
            return;
        }

        if (! $brand->users()->where('users.id', $user->id)->exists()) {
            abort(403, 'You do not have access to this brand.');
        }
    }

    /**
     * Transform brand model for table response.
     */
    private function transformBrandForTable(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'ulid' => $brand->ulid,
            'brand_name' => $brand->name,
            'brand_slug' => $brand->slug,
            'company_name' => $brand->company_name,
            'status' => $brand->status,
            'brand_logo' => $brand->brand_logo,
            'brand_events_count' => $brand->brand_events_count,
            'members_count' => $brand->users_count ?? 0,
            'business_categories' => $brand->business_categories_list,
            'events' => $brand->brandEvents->map(fn ($be) => [
                'id' => $be->event->id,
                'title' => $be->event->title,
                'slug' => $be->event->slug,
                'poster_image' => $be->event->poster_image,
                'booth_number' => $be->booth_number,
                'status' => $be->status,
            ]),
            'company_email' => $brand->company_email,
            'company_phone' => $brand->company_phone,
            'created_at' => $brand->created_at,
        ];
    }

    /**
     * Handle temporary file upload and move to media collection.
     */
    private function handleTemporaryUpload(Request $request, Brand $brand, string $fieldName, string $collection): void
    {
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $brand->clearMediaCollection($collection);

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

        $brand->clearMediaCollection($collection);

        $brand->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }
}
