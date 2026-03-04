<?php

namespace App\Http\Controllers\Api;

use App\Exports\FormResponsesExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\FormResponseResource;
use App\Models\Form;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class FormResponseController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Form $form): JsonResponse
    {
        $this->authorize('view', $form);

        $responses = $form->responses()
            ->orderBy('submitted_at', 'desc')
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
            ->delete();

        return response()->json([
            'message' => 'Responses deleted successfully',
        ]);
    }

    public function export(Request $request, Form $form)
    {
        $this->authorize('view', $form);

        $filters = $request->only(['search']);
        $sort = $request->input('sort', '-submitted_at');

        $filename = Str::slug($form->title).'-responses-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(
            new FormResponsesExport($form, $filters ?: null, $sort),
            $filename
        );
    }
}
