<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Exports\ContactsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactIndexResource;
use App\Http\Resources\ContactResource;
use App\Imports\ContactsImport;
use App\Jobs\BulkForceDeleteContacts;
use App\Jobs\BulkSoftDeleteContacts;
use App\Jobs\ExportContacts;
use App\Jobs\ProcessExcelImport;
use App\Jobs\RemoveDuplicateContacts;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Tags\Tag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactController extends Controller
{
    /**
     * List contacts with filters, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contact::query()
            ->with(['tags', 'projects.media', 'creator'])
            ->withCount(['projects']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $contacts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ContactIndexResource::collection($contacts)->resolve(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    /**
     * Create a new contact.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $contactTypes = $validated['contact_types'] ?? null;
        $businessCategories = $validated['business_categories'] ?? null;
        $tags = $validated['tags'] ?? null;
        $projectIds = $validated['project_ids'] ?? null;
        unset($validated['contact_types'], $validated['business_categories'], $validated['tags'], $validated['project_ids']);

        $contact = Contact::create($validated);

        if ($contactTypes !== null) {
            $contact->syncContactTypes($contactTypes);
        }

        if ($businessCategories !== null) {
            $contact->syncBusinessCategories($businessCategories);
        }

        if ($tags !== null) {
            $contact->syncContactTags($tags);
        }

        if ($projectIds !== null) {
            $contact->projects()->sync($projectIds);
        }

        $contact->load('tags');

        return response()->json([
            'data' => new ContactResource($contact),
            'message' => 'Contact created successfully',
        ], 201);
    }

    /**
     * Show a single contact with relationships.
     */
    public function show(Contact $contact): JsonResponse
    {
        $contact->load(['tags', 'projects']);

        // Collect business category options
        $businessCategoryOptions = Tag::withType('business_category')
            ->ordered()
            ->pluck('name')
            ->toArray();

        // Also include project-scoped business categories
        $projectIds = $contact->projects->pluck('id');
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

        return response()->json([
            'data' => new ContactResource($contact),
            'contact_type_options' => ContactType::options(),
            'business_category_options' => $businessCategoryOptions,
        ]);
    }

    /**
     * Update a contact.
     */
    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $validated = $request->validated();

        $contactTypes = $validated['contact_types'] ?? null;
        $businessCategories = $validated['business_categories'] ?? null;
        $tags = $validated['tags'] ?? null;
        $projectIds = $validated['project_ids'] ?? null;
        unset($validated['contact_types'], $validated['business_categories'], $validated['tags'], $validated['project_ids']);

        $contact->update($validated);

        if ($contactTypes !== null) {
            $contact->syncContactTypes($contactTypes);
        }

        if ($businessCategories !== null) {
            $contact->syncBusinessCategories($businessCategories);
        }

        if ($tags !== null) {
            $contact->syncContactTags($tags);
        }

        if ($projectIds !== null) {
            $contact->projects()->sync($projectIds);
        }

        $contact->load(['tags', 'projects']);

        return response()->json([
            'data' => new ContactResource($contact),
            'message' => 'Contact updated successfully',
        ]);
    }

    /**
     * Delete a contact (soft delete).
     */
    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json(['message' => 'Contact deleted successfully']);
    }

    /**
     * Bulk soft-delete contacts (queued).
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
            'message' => 'Preparing to delete contacts...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkSoftDeleteContacts::dispatch(
            $jobId,
            $validated['ids'],
            auth()->id(),
        );

        return response()->json(['job_id' => $jobId]);
    }

    /**
     * Quick search contacts by name, company_name, or email.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1'],
        ]);

        $query = Contact::query()->orderBy('name');

        $term = trim($request->input('q', ''));

        if ($term && $term !== '*') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('company_name', 'ilike', "%{$term}%")
                    ->orWhereJsonContains('emails', $term);
            });
        }

        $contacts = $query
            ->limit(50)
            ->get()
            ->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'ulid' => $contact->ulid,
                'name' => $contact->name,
                'company_name' => $contact->company_name,
                'primary_email' => ($contact->emails ?? [])[0] ?? null,
            ]);

        return response()->json(['data' => $contacts]);
    }

    /**
     * Export contacts to Excel (queued).
     */
    public function export(Request $request): JsonResponse
    {
        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }
        if ($status = $request->input('filter_status')) {
            $filters['status'] = $status;
        }
        if ($source = $request->input('filter_source')) {
            $filters['source'] = $source;
        }

        $sort = $request->input('sort', 'name');

        $jobId = Str::uuid()->toString();

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => 0,
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Preparing export...',
            'error_message' => null,
        ], now()->addMinutes(30));

        ExportContacts::dispatch($jobId, $filters, $sort);

        return response()->json(['job_id' => $jobId]);
    }

    /**
     * Download import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $filename = 'contacts_import_template.xlsx';

        return Excel::download(new ContactsTemplateExport, $filename);
    }

    /**
     * Import contacts from Excel (queued).
     */
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
            ContactsImport::class,
            $tempFolder,
        );

        return response()->json([
            'import_id' => $importId,
        ]);
    }

    /**
     * Update a contact's status.
     */
    public function updateStatus(Request $request, Contact $contact): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:'.implode(',', ContactStatus::values())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contact->update([
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * Scan for duplicate contacts.
     */
    public function scanDuplicates(): JsonResponse
    {
        $groups = $this->findDuplicateGroups();

        $previewGroups = array_slice($groups, 0, 50);

        $duplicateCount = array_reduce($groups, fn (int $carry, array $group) => $carry + count($group['duplicates']), 0);

        return response()->json([
            'duplicate_count' => $duplicateCount,
            'group_count' => count($groups),
            'groups' => $previewGroups,
        ]);
    }

    /**
     * Remove duplicate contacts by dispatching a queued job.
     */
    public function removeDuplicates(): JsonResponse
    {
        $groups = $this->findDuplicateGroups();

        if (empty($groups)) {
            return response()->json([
                'removed_count' => 0,
                'message' => 'No duplicates found',
            ]);
        }

        $jobId = Str::uuid()->toString();

        $totalDuplicates = array_reduce(
            $groups,
            fn (int $carry, array $group) => $carry + count($group['duplicates']),
            0,
        );

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => $totalDuplicates,
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Preparing to remove duplicates...',
            'error_message' => null,
        ], now()->addMinutes(30));

        RemoveDuplicateContacts::dispatch($jobId, $groups);

        return response()->json(['job_id' => $jobId]);
    }

    /**
     * Find groups of duplicate contacts.
     *
     * @return array<int, array{keep: array, duplicates: array}>
     */
    private function findDuplicateGroups(): array
    {
        $contacts = Contact::query()
            ->select(['id', 'ulid', 'name', 'company_name', 'emails', 'phones', 'created_at'])
            ->orderBy('created_at')
            ->get();

        // Group by normalized name
        $nameGroups = [];
        foreach ($contacts as $contact) {
            $normalizedName = mb_strtolower(trim($contact->name));
            if ($normalizedName === '') {
                continue;
            }
            $nameGroups[$normalizedName][] = $contact;
        }

        $duplicateGroups = [];

        foreach ($nameGroups as $groupContacts) {
            if (count($groupContacts) < 2) {
                continue;
            }

            // Within same-name group, find contacts that also share company, email, or phone
            $processed = [];

            foreach ($groupContacts as $i => $contact) {
                if (in_array($contact->id, $processed)) {
                    continue;
                }

                $duplicates = [];

                for ($j = $i + 1; $j < count($groupContacts); $j++) {
                    $other = $groupContacts[$j];
                    if (in_array($other->id, $processed)) {
                        continue;
                    }

                    if ($this->isDuplicate($contact, $other)) {
                        $duplicates[] = $other;
                        $processed[] = $other->id;
                    }
                }

                if (! empty($duplicates)) {
                    $processed[] = $contact->id;
                    $duplicateGroups[] = [
                        'keep' => [
                            'id' => $contact->id,
                            'ulid' => $contact->ulid,
                            'name' => $contact->name,
                            'company_name' => $contact->company_name,
                            'emails' => $contact->emails,
                            'phones' => $contact->phones,
                            'created_at' => $contact->created_at,
                        ],
                        'duplicates' => array_map(fn (Contact $dup) => [
                            'id' => $dup->id,
                            'ulid' => $dup->ulid,
                            'name' => $dup->name,
                            'company_name' => $dup->company_name,
                            'emails' => $dup->emails,
                            'phones' => $dup->phones,
                            'created_at' => $dup->created_at,
                        ], $duplicates),
                    ];
                }
            }
        }

        return $duplicateGroups;
    }

    /**
     * Check if two contacts are duplicates (same name assumed).
     */
    private function isDuplicate(Contact $a, Contact $b): bool
    {
        // Check company name match
        $companyA = mb_strtolower(trim($a->company_name ?? ''));
        $companyB = mb_strtolower(trim($b->company_name ?? ''));
        if ($companyA !== '' && $companyB !== '' && $companyA === $companyB) {
            return true;
        }

        // Check email intersection
        $emailsA = array_map('mb_strtolower', $a->emails ?? []);
        $emailsB = array_map('mb_strtolower', $b->emails ?? []);
        if (! empty($emailsA) && ! empty($emailsB) && ! empty(array_intersect($emailsA, $emailsB))) {
            return true;
        }

        // Check phone intersection (normalized: digits only)
        $phonesA = array_map(fn (string $p) => preg_replace('/\D/', '', $p), $a->phones ?? []);
        $phonesB = array_map(fn (string $p) => preg_replace('/\D/', '', $p), $b->phones ?? []);
        $phonesA = array_filter($phonesA);
        $phonesB = array_filter($phonesB);
        if (! empty($phonesA) && ! empty($phonesB) && ! empty(array_intersect($phonesA, $phonesB))) {
            return true;
        }

        return false;
    }

    /**
     * List trashed contacts.
     */
    public function trash(Request $request): JsonResponse
    {
        $query = Contact::onlyTrashed()
            ->with(['tags', 'projects.media', 'creator', 'deleter']);

        $this->applyFilters($query, $request);

        // Default sort by deleted_at desc
        $sortField = $request->input('sort', '-deleted_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $trashFieldMap = [
            'name' => 'name',
            'company_name' => 'company_name',
            'status' => 'status',
            'source' => 'source',
            'created_at' => 'created_at',
            'deleted_at' => 'deleted_at',
        ];

        if (isset($trashFieldMap[$field])) {
            $query->orderBy($trashFieldMap[$field], $direction);
        } else {
            $query->orderBy('deleted_at', 'desc');
        }

        $contacts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $contacts->map(fn ($contact) => [
                ...ContactIndexResource::make($contact)->resolve(),
                'deleted_at' => $contact->deleted_at?->toISOString(),
                'deleter' => $contact->deleter ? ['name' => $contact->deleter->name] : null,
            ]),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    /**
     * Restore a trashed contact.
     */
    public function restore(string $id): JsonResponse
    {
        $contact = Contact::onlyTrashed()->findOrFail($id);
        $contact->restore();

        return response()->json(['message' => 'Contact restored successfully']);
    }

    /**
     * Bulk restore trashed contacts.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restored = 0;
        foreach ($validated['ids'] as $id) {
            $contact = Contact::onlyTrashed()->find($id);
            if ($contact) {
                $contact->restore();
                $restored++;
            }
        }

        return response()->json([
            'message' => "{$restored} contact(s) restored successfully",
            'restored_count' => $restored,
        ]);
    }

    /**
     * Permanently delete a trashed contact.
     */
    public function forceDestroy(string $id): JsonResponse
    {
        $contact = Contact::onlyTrashed()->findOrFail($id);
        $contact->forceDelete();

        return response()->json(['message' => 'Contact permanently deleted']);
    }

    /**
     * Bulk permanently delete trashed contacts (queued).
     */
    public function bulkForceDestroy(Request $request): JsonResponse
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
            'message' => 'Preparing to permanently delete contacts...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkForceDeleteContacts::dispatch($jobId, $validated['ids']);

        return response()->json(['job_id' => $jobId]);
    }

    /**
     * Permanently delete ALL trashed contacts (queued).
     */
    public function emptyTrash(): JsonResponse
    {
        $ids = Contact::onlyTrashed()->pluck('id')->all();

        if (empty($ids)) {
            return response()->json(['message' => 'No trashed contacts to delete'], 404);
        }

        $jobId = Str::uuid()->toString();

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => count($ids),
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Preparing to permanently delete all contacts...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkForceDeleteContacts::dispatch($jobId, $ids);

        return response()->json(['job_id' => $jobId]);
    }

    private function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('company_name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('job_title', 'ilike', "%{$searchTerm}%");
            });
        }

        // Status filter
        if ($status = $request->input('filter_status')) {
            $statuses = is_array($status) ? $status : explode(',', $status);
            $statuses = array_filter($statuses);

            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } elseif (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            }
        }

        // Source filter
        if ($source = $request->input('filter_source')) {
            $sources = is_array($source) ? $source : explode(',', $source);
            $sources = array_filter($sources);

            if (count($sources) > 1) {
                $query->whereIn('source', $sources);
            } elseif (count($sources) === 1) {
                $query->where('source', $sources[0]);
            }
        }

        // Project filter
        if ($projectId = $request->input('filter_project')) {
            $query->forProject((int) $projectId);
        }

        // Contact type filter (via tags)
        if ($type = $request->input('filter_type')) {
            $types = is_array($type) ? $type : explode(',', $type);
            $types = array_filter($types);

            if (! empty($types)) {
                $query->withAnyTags($types, 'contact_type');
            }
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', 'name');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $fieldMap = [
            'name' => 'name',
            'company_name' => 'company_name',
            'status' => 'status',
            'source' => 'source',
            'created_at' => 'created_at',
            'deleted_at' => 'deleted_at',
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }
}
