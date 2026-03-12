<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactType;
use App\Exports\ContactsExport;
use App\Exports\ContactsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactIndexResource;
use App\Http\Resources\ContactResource;
use App\Imports\ContactsImport;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
            ->with(['tags'])
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
     * Export contacts to Excel.
     */
    public function export(Request $request): BinaryFileResponse
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

        $export = new ContactsExport($filters, $sort);

        $filename = 'contacts_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
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
     * Import contacts from Excel.
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

            $import = new ContactsImport;
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
                'message' => 'Contacts imported successfully',
                'imported_count' => $importedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('Contact import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import contacts',
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
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }
}
