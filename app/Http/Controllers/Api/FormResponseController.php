<?php

namespace App\Http\Controllers\Api;

use App\Exports\FormResponsesExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\FormResponseResource;
use App\Models\Form;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class FormResponseController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Form $form): JsonResponse
    {
        $this->authorize('view', $form);

        $query = $form->responses();

        if ($statuses = array_filter(explode(',', (string) $request->input('filter_status')))) {
            $query->whereIn('status', $statuses);
        }

        if ($search = trim((string) $request->input('filter_search'))) {
            $query->search($search);
        }

        $sortBy = in_array($request->input('sort_by'), ['submitted_at', 'status', 'respondent_email'])
            ? $request->input('sort_by')
            : 'submitted_at';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $responses = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => FormResponseResource::collection($responses->items()),
            'meta' => [
                'current_page' => $responses->currentPage(),
                'last_page' => $responses->lastPage(),
                'per_page' => $responses->perPage(),
                'total' => $responses->total(),
            ],
        ]);
    }

    public function downloadFile(Request $request, Form $form, string $ulid, string $fieldUlid): mixed
    {
        $this->authorize('view', $form);

        $response = $form->responses()->where('ulid', $ulid)->firstOrFail();

        $value = $response->response_data[$fieldUlid] ?? null;
        $paths = is_array($value) ? array_values($value) : [$value];
        $index = (int) $request->input('index', 0);
        $path = $paths[$index] ?? null;

        if (! is_string($path)
            || ! str_starts_with($path, "form-uploads/{$form->id}/")
            || str_contains($path, '..')
            || ! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('local')->download($path);
    }

    public function destroy(Form $form, string $ulid): JsonResponse
    {
        $this->authorize('update', $form);

        $response = $form->responses()->where('ulid', $ulid)->firstOrFail();

        $response->delete();

        return response()->json([
            'message' => 'Response deleted successfully',
        ]);
    }

    public function bulkUpdateStatus(Request $request, Form $form): JsonResponse
    {
        $this->authorize('update', $form);

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', 'string', 'in:new,read,starred,spam'],
        ]);

        $form->responses()
            ->whereIn('id', $request->input('ids'))
            ->update(['status' => $request->input('status')]);

        return response()->json([
            'message' => 'Status updated successfully',
        ]);
    }

    public function bulkDestroy(Request $request, Form $form): JsonResponse
    {
        $this->authorize('update', $form);

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $form->responses()
            ->whereIn('id', $request->input('ids'))
            ->get()
            ->each
            ->delete();

        return response()->json([
            'message' => 'Responses deleted successfully',
        ]);
    }

    public function export(Request $request, Form $form)
    {
        $this->authorize('view', $form);

        $request->validate([
            'format' => ['nullable', 'in:xlsx,csv'],
            'ids' => ['nullable', 'string'],
        ]);

        $format = $request->input('format', 'xlsx');
        $ids = array_filter(array_map('intval', explode(',', (string) $request->input('ids'))));

        $filters = array_filter([
            'search' => $request->input('search'),
            'ids' => $ids ?: null,
        ]);
        $sort = $request->input('sort', '-submitted_at');

        $filename = Str::slug($form->title).'-responses-'.now()->format('Y-m-d').'.'.$format;

        activity()
            ->causedBy($request->user())
            ->performedOn($form)
            ->event('exported')
            ->withProperties([
                'project_id' => $form->project_id ?? null,
                'model_type' => 'FormResponse',
                'form_id' => $form->id,
                'filename' => $filename,
                'format' => $format,
                'selected_count' => count($ids) ?: null,
            ])
            ->log("Exported responses for form: {$form->title}");

        return Excel::download(
            new FormResponsesExport($form, $filters ?: null, $sort),
            $filename,
            $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : null
        );
    }
}
