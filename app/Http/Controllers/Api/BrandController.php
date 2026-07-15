<?php

namespace App\Http\Controllers\Api;

use App\Exports\BrandsExport;
use App\Exports\BrandsTemplateExport;
use App\Helpers\LinkNormalizer;
use App\Helpers\PhoneCountryHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectCustomFieldResource;
use App\Imports\BrandsImport;
use App\Jobs\BulkSoftDeleteBrands;
use App\Jobs\ProcessExcelImport;
use App\Models\Brand;
use App\Models\CustomField;
use App\Models\Project;
use App\Models\User;
use App\Support\CustomFieldValidation;
use App\Support\ImageDimensions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\ResponseCache\Facades\ResponseCache;
use Spatie\Tags\Tag;
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

        $this->scopeToVisibleBrands($query, $user);

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

        $brand->load(['media', 'tags', 'links', 'users.media', 'creator', 'updater', 'brandEvents.event.project']);

        // Scope options to the brand's active event project(s) so it never shows
        // categories or custom fields from unrelated projects/past editions.
        $projectIds = $brand->activeProjectIds();
        $businessCategoryOptions = [];

        foreach ($projectIds as $projectId) {
            $businessCategoryOptions = array_merge(
                $businessCategoryOptions,
                Tag::withType("business_category:{$projectId}")
                    ->ordered()
                    ->pluck('name')
                    ->toArray()
            );
        }

        $businessCategoryOptions = array_values(array_unique($businessCategoryOptions));

        $customFieldDefinitions = $this->brandFieldDefinitionsFor($projectIds);

        return response()->json([
            'data' => [
                'id' => $brand->id,
                'ulid' => $brand->ulid,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'company_name' => $brand->company_name,
                'address' => $brand->address,
                'company_email' => $brand->company_email,
                'company_phone' => $brand->company_phone,
                'custom_fields' => $brand->custom_fields,
                'status' => $brand->status,
                'visibility' => $brand->visibility,
                'profile_image' => $brand->profile_image,
                'brand_logo' => $brand->brand_logo,
                'business_categories' => $brand->business_categories_list,
                'links' => $brand->links->map(fn ($link) => [
                    'label' => $link->label,
                    'url' => $link->url,
                ]),
                'members' => $brand->users->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->relationLoaded('media') ? $user->getMediaUrls('profile_image') : null,
                ]),
                'brand_events' => $brand->brandEvents->map(fn ($be) => [
                    'id' => $be->id,
                    'event' => $be->event ? [
                        'id' => $be->event->id,
                        'slug' => $be->event->slug,
                        'title' => $be->event->title,
                        'edition_number' => $be->event->edition_number,
                        'project' => $be->event->project ? [
                            'id' => $be->event->project->id,
                            'username' => $be->event->project->username,
                            'name' => $be->event->project->name,
                        ] : null,
                    ] : null,
                ])->values(),
                'created_at' => $brand->created_at,
                'updated_at' => $brand->updated_at,
                'created_by' => $brand->creator ? ['id' => $brand->creator->id, 'name' => $brand->creator->name] : null,
                'updated_by' => $brand->updater ? ['id' => $brand->updater->id, 'name' => $brand->updater->name] : null,
            ],
            'business_category_options' => $businessCategoryOptions,
            'custom_field_definitions' => ProjectCustomFieldResource::collection($customFieldDefinitions),
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
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:1000'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', Email::default(), 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'business_categories' => ['nullable', 'array'],
            'business_categories.*' => ['string', 'max:100'],
            'tmp_profile_image' => ['nullable', 'string'],
            'delete_profile_image' => ['nullable', 'boolean'],
            'tmp_brand_logo' => ['nullable', 'string'],
            'delete_brand_logo' => ['nullable', 'boolean'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'string', 'max:500'],
            'project_custom_fields' => ['nullable', 'array'],
        ]);

        // Normalize input data
        if (isset($validated['company_email'])) {
            $validated['company_email'] = strtolower(trim($validated['company_email']));
        }
        if (isset($validated['company_phone']) && $validated['company_phone'] !== null) {
            $validated['company_phone'] = PhoneCountryHelper::normalizePhoneNumber($validated['company_phone']);
        }
        foreach (['name', 'company_name'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim($validated[$field]);
            }
        }

        $categories = $validated['business_categories'] ?? null;
        $links = $validated['links'] ?? null;
        $projectCustomFields = $validated['project_custom_fields'] ?? null;
        unset($validated['business_categories'], $validated['tmp_profile_image'], $validated['delete_profile_image'], $validated['tmp_brand_logo'], $validated['delete_brand_logo'], $validated['links'], $validated['project_custom_fields']);

        $brand->update($validated);

        if ($categories !== null) {
            $brand->syncBusinessCategories($categories);
        }

        $this->handleTemporaryUpload($request, $brand, 'tmp_profile_image', 'profile_image');
        $this->handleTemporaryUpload($request, $brand, 'tmp_brand_logo', 'brand_logo');

        // Save project custom field values to brands.custom_fields; values are
        // only validated when the payload actually carries the key, so partial
        // updates that skip brand fields are never blocked.
        if ($projectCustomFields !== null) {
            $customFieldDefinitions = $this->brandFieldDefinitionsFor($brand->activeProjectIds());

            $errors = CustomFieldValidation::errorsFor(
                $customFieldDefinitions,
                (array) $request->input('project_custom_fields'),
                'project_custom_fields',
                'key',
            );

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $cleanedValues = $brand->custom_fields ?? [];

            foreach ($customFieldDefinitions as $fieldDef) {
                if (array_key_exists($fieldDef->key, $projectCustomFields)) {
                    $cleanedValues[$fieldDef->key] = $projectCustomFields[$fieldDef->key];
                }
            }

            $brand->update(['custom_fields' => $cleanedValues]);
        }

        // Sync links if provided
        if ($links !== null) {
            $links = LinkNormalizer::normalizeAll($links);
            $brand->links()->delete();

            foreach ($links as $index => $linkData) {
                $brand->links()->create([
                    'label' => $linkData['label'],
                    'url' => $linkData['url'],
                    'order' => $index,
                ]);
            }
        }

        // $brand->update() above fires the trait clear BEFORE categories, logo
        // and links are written, so clear once more after all writes to avoid
        // repopulating the cache with stale data on a concurrent public hit.
        ResponseCache::clear(['brands']);

        $brand->load('media');

        return response()->json([
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'profile_image' => $brand->profile_image,
                'brand_logo' => $brand->brand_logo,
            ],
            'message' => 'Brand updated successfully',
        ]);
    }

    /**
     * Bulk delete brands (soft delete, queued with progress).
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
            'message' => 'Preparing to delete brands...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkSoftDeleteBrands::dispatch(
            $jobId,
            $validated['ids'],
            auth()->id(),
        );

        return response()->json(['job_id' => $jobId]);
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
     * List trashed brands.
     */
    public function trash(Request $request): JsonResponse
    {
        $query = Brand::onlyTrashed()
            ->with(['media', 'deleter'])
            ->withCount(['brandEvents', 'users']);

        $this->applyFilters($query, $request);

        $sortField = $request->input('sort', '-deleted_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $trashFieldMap = [
            'brand_name' => 'name',
            'company_name' => 'company_name',
            'status' => 'status',
            'created_at' => 'created_at',
            'deleted_at' => 'deleted_at',
        ];

        if (isset($trashFieldMap[$field])) {
            $query->orderBy($trashFieldMap[$field], $direction);
        } else {
            $query->orderBy('deleted_at', 'desc');
        }

        $brands = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $brands->map(fn (Brand $brand) => [
                ...$this->transformBrandForTable($brand),
                'deleted_at' => $brand->deleted_at?->toISOString(),
                'deleter' => $brand->deleter ? ['name' => $brand->deleter->name] : null,
            ]),
            'meta' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
            ],
        ]);
    }

    /**
     * Restore a single trashed brand.
     */
    public function restore(string $id): JsonResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $brand->restore();

        return response()->json(['message' => 'Brand restored successfully']);
    }

    /**
     * Bulk restore trashed brands.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restored = 0;
        foreach ($validated['ids'] as $id) {
            $brand = Brand::onlyTrashed()->find($id);
            if ($brand) {
                $brand->restore();
                $restored++;
            }
        }

        return response()->json([
            'message' => "{$restored} brand(s) restored successfully",
            'restored_count' => $restored,
        ]);
    }

    /**
     * Permanently delete a single trashed brand.
     */
    public function forceDestroy(string $id): JsonResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $brand->forceDelete();

        return response()->json(['message' => 'Brand permanently deleted']);
    }

    /**
     * Bulk permanently delete trashed brands.
     */
    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deletedCount = 0;
        foreach ($validated['ids'] as $id) {
            $brand = Brand::onlyTrashed()->find($id);
            if ($brand) {
                $brand->forceDelete();
                $deletedCount++;
            }
        }

        return response()->json([
            'message' => "{$deletedCount} brand(s) permanently deleted",
            'deleted_count' => $deletedCount,
        ]);
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
                'country' => $brand->address['country'] ?? null,
                'profile_image' => $brand->profile_image,
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
        foreach (['country', 'province', 'city'] as $key) {
            if ($value = $request->input("filter_{$key}")) {
                $filters[$key] = $value;
            }
        }

        $sort = $request->input('sort', 'name');

        $export = new BrandsExport($filters, $sort);

        $filename = 'brands_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'model_type' => 'Brand',
                'filename' => $filename,
            ])
            ->log('Exported brands');

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
            BrandsImport::class,
            $tempFolder,
        );

        return response()->json(['import_id' => $importId]);
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

        // Country filter
        if ($country = $request->input('filter_country')) {
            $countries = is_array($country) ? $country : explode(',', $country);
            $countries = array_filter($countries);

            if (! empty($countries)) {
                $query->where(function ($q) use ($countries) {
                    foreach ($countries as $c) {
                        $q->orWhereRaw("address->>'country' ilike ?", ["%{$c}%"]);
                    }
                });
            }
        }

        // Province filter
        if ($province = $request->input('filter_province')) {
            $provinces = is_array($province) ? $province : explode(',', $province);
            $provinces = array_filter($provinces);

            if (! empty($provinces)) {
                $query->where(function ($q) use ($provinces) {
                    foreach ($provinces as $p) {
                        $q->orWhereRaw("address->>'province' ilike ?", ["%{$p}%"]);
                    }
                });
            }
        }

        // City filter
        if ($city = $request->input('filter_city')) {
            $cities = is_array($city) ? $city : explode(',', $city);
            $cities = array_filter($cities);

            if (! empty($cities)) {
                $query->where(function ($q) use ($cities) {
                    foreach ($cities as $c) {
                        $q->orWhereRaw("address->>'city' ilike ?", ["%{$c}%"]);
                    }
                });
            }
        }
    }

    /**
     * Exhibitors only ever see the brands they are assigned to.
     */
    private function scopeToVisibleBrands($query, ?User $user): void
    {
        if ($user && ! $user->hasRole(['master', 'admin', 'staff'])) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }
    }

    /**
     * Return distinct filter options for the brands list. The facets must respect
     * the same visibility scope as index(), otherwise an exhibitor sees locations
     * belonging to brands they cannot list.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        $distinctAddressValues = function (string $key) use ($user): array {
            $query = Brand::query()
                ->whereNotNull('address')
                ->whereRaw("address->>'{$key}' IS NOT NULL AND address->>'{$key}' != ''")
                ->selectRaw("DISTINCT address->>'{$key}' as value")
                ->orderByRaw("address->>'{$key}'");

            $this->scopeToVisibleBrands($query, $user);

            return $query->pluck('value')->toArray();
        };

        return response()->json([
            'countries' => $distinctAddressValues('country'),
            'provinces' => $distinctAddressValues('province'),
            'cities' => $distinctAddressValues('city'),
        ]);
    }

    /**
     * List brand members.
     */
    public function members(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeExhibitorAccess($request->user(), $brand);

        $members = $brand->users()->with('media')->get()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->relationLoaded('media') ? $user->getMediaUrls('profile_image') : null,
        ]);

        return response()->json(['data' => $members]);
    }

    /**
     * Add member to brand.
     */
    public function addMember(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeExhibitorAccess($request->user(), $brand);

        $validated = $request->validate([
            'email' => ['required', Email::default()],
        ]);

        $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])->first();

        if (! $user) {
            $user = User::create([
                'name' => Str::before($validated['email'], '@'),
                'email' => strtolower(trim($validated['email'])),
                'password' => bcrypt(Str::random(16)),
                'email_verified_at' => now(),
            ]);
        } elseif ($user->trashed()) {
            $user->restore();
        }

        if (! $user->hasRole('exhibitor')) {
            $user->assignRole('exhibitor');
        }

        if (! $brand->users()->where('user_id', $user->id)->exists()) {
            $brand->users()->attach($user->id);
        }

        return response()->json([
            'message' => 'Member added to brand successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getMediaUrls('profile_image'),
            ],
        ], 201);
    }

    /**
     * Remove member from brand.
     */
    public function removeMember(Request $request, Brand $brand, int $userId): JsonResponse
    {
        $this->authorizeExhibitorAccess($request->user(), $brand);

        $brand->users()->detach($userId);

        return response()->json(['message' => 'Member removed from brand successfully.']);
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
     * Brand-field definitions for the given projects, de-duplicated by their
     * (English) label so a project that accidentally holds two fields with the
     * same name never surfaces a duplicate column/input.
     *
     * @param  array<int, int>  $projectIds
     * @return Collection<int, CustomField>
     */
    private function brandFieldDefinitionsFor(array $projectIds): Collection
    {
        return CustomField::query()
            ->context(CustomField::CONTEXT_BRAND)
            ->where('fieldable_type', Project::class)
            ->whereIn('fieldable_id', $projectIds)
            ->ordered()
            ->get()
            ->groupBy(fn (CustomField $field) => mb_strtolower(trim((string) $field->getTranslation('label', 'en'))))
            ->map(fn (Collection $group) => $group->sortBy('id')->first())
            ->values();
    }

    /**
     * Ensure non-staff users can only access their own brands.
     */
    private function authorizeExhibitorAccess(User $user, Brand $brand): void
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
            'profile_image' => $brand->profile_image,
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

            // MediaLibrary mutations do not fire the Brand saved event, so the
            // ClearsResponseCache trait never runs. Bust the cache after the
            // media change so the public brand payload reflects the removed logo.
            ResponseCache::clear(['brands']);

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

        $absolutePath = Storage::disk('local')->path($filePath);

        if ($collection === 'profile_image'
            && ! ImageDimensions::meetsMinimum($absolutePath, $metadata['mime_type'] ?? '')) {
            throw ValidationException::withMessages([
                'tmp_profile_image' => 'Profile image must be at least 1000x1000 pixels.',
            ]);
        }

        $brand->clearMediaCollection($collection);

        $brand->addMedia($absolutePath)
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");

        // MediaLibrary add runs after Brand::update() in update(), so the
        // trait's earlier clear is repopulated with the old logo. Bust again
        // here, after the new media is committed.
        ResponseCache::clear(['brands']);
    }
}
