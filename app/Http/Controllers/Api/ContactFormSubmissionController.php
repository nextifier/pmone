<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactFormStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactFormSubmissionIndexResource;
use App\Http\Resources\ContactFormSubmissionResource;
use App\Models\ContactFormSubmission;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactFormSubmissionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $query = ContactFormSubmission::query()->with(['project', 'followedUpByUser']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        // Client-only mode - return all data for client-side pagination
        if ($request->boolean('client_only')) {
            $submissions = $query->get();

            return response()->json([
                'data' => ContactFormSubmissionIndexResource::collection($submissions),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $submissions->count(),
                    'total' => $submissions->count(),
                ],
            ]);
        }

        $submissions = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ContactFormSubmissionIndexResource::collection($submissions->items()),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        // Search filter (search in form data)
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTerm = strtolower($searchTerm);
                $q->whereRaw('LOWER(subject) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(form_data) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Status filter
        if ($statuses = $request->input('filter_status')) {
            $statusArray = explode(',', $statuses);
            $query->whereIn('status', $statusArray);
        }

        // Project filter
        if ($projectId = $request->input('filter_project')) {
            $query->where('project_id', $projectId);
        }

        // Followed up filter
        if ($request->has('filter_followed_up')) {
            $followedUp = $request->boolean('filter_followed_up');
            if ($followedUp) {
                $query->whereNotNull('followed_up_at');
            } else {
                $query->whereNull('followed_up_at');
            }
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if (in_array($field, ['subject', 'status', 'created_at', 'updated_at', 'followed_up_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    public function show(ContactFormSubmission $contactFormSubmission): JsonResponse
    {
        $contactFormSubmission->load(['project', 'followedUpByUser']);

        return response()->json([
            'data' => new ContactFormSubmissionResource($contactFormSubmission),
        ]);
    }

    public function updateStatus(Request $request, ContactFormSubmission $contactFormSubmission): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:'.implode(',', ContactFormStatus::values())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $contactFormSubmission->update([
                'status' => $request->input('status'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'data' => new ContactFormSubmissionResource($contactFormSubmission),
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to update contact form submission status', [
                'error' => $e->getMessage(),
                'submission_id' => $contactFormSubmission->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function markAsFollowedUp(Request $request, ContactFormSubmission $contactFormSubmission): JsonResponse
    {
        try {
            $contactFormSubmission->markAsFollowedUp($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Marked as followed up successfully',
                'data' => new ContactFormSubmissionResource($contactFormSubmission->fresh(['project', 'followedUpByUser'])),
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to mark contact form submission as followed up', [
                'error' => $e->getMessage(),
                'submission_id' => $contactFormSubmission->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as followed up',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, ContactFormSubmission $contactFormSubmission): JsonResponse
    {
        try {
            $contactFormSubmission->update(['deleted_by' => $request->user()->id]);
            $contactFormSubmission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contact form submission deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to delete contact form submission', [
                'error' => $e->getMessage(),
                'submission_id' => $contactFormSubmission->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete submission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:contact_form_submissions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($request->input('ids') as $id) {
            try {
                $submission = ContactFormSubmission::find($id);
                if ($submission) {
                    $submission->update(['deleted_by' => $request->user()->id]);
                    $submission->delete();
                    $deletedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} submission(s) deleted successfully",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $query = ContactFormSubmission::onlyTrashed()->with(['project', 'followedUpByUser', 'deleter']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        // Client-only mode - return all data for client-side pagination
        if ($request->boolean('client_only')) {
            $submissions = $query->get();

            return response()->json([
                'data' => ContactFormSubmissionIndexResource::collection($submissions),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $submissions->count(),
                    'total' => $submissions->count(),
                ],
            ]);
        }

        $submissions = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ContactFormSubmissionIndexResource::collection($submissions->items()),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        try {
            $submission = ContactFormSubmission::onlyTrashed()->findOrFail($id);
            $submission->restore();
            $submission->update(['deleted_by' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Contact form submission restored successfully',
                'data' => new ContactFormSubmissionResource($submission->fresh(['project', 'followedUpByUser'])),
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to restore contact form submission', [
                'error' => $e->getMessage(),
                'submission_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore submission',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        $restoredCount = 0;
        $errors = [];

        foreach ($request->input('ids') as $id) {
            try {
                $submission = ContactFormSubmission::onlyTrashed()->find($id);
                if ($submission) {
                    $submission->restore();
                    $submission->update(['deleted_by' => null]);
                    $restoredCount++;
                }
            } catch (\Exception $e) {
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$restoredCount} submission(s) restored successfully",
            'restored_count' => $restoredCount,
            'errors' => $errors,
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        try {
            $submission = ContactFormSubmission::onlyTrashed()->findOrFail($id);
            $submission->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Contact form submission permanently deleted',
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to permanently delete contact form submission', [
                'error' => $e->getMessage(),
                'submission_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete submission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkForceDelete(Request $request): JsonResponse
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

        $deletedCount = 0;
        $errors = [];

        foreach ($request->input('ids') as $id) {
            try {
                $submission = ContactFormSubmission::onlyTrashed()->find($id);
                if ($submission) {
                    $submission->forceDelete();
                    $deletedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} submission(s) permanently deleted",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }
}
