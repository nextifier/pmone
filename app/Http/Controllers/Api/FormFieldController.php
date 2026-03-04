<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormFieldRequest;
use App\Http\Requests\UpdateFormFieldRequest;
use App\Http\Resources\FormFieldResource;
use App\Models\Form;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormFieldController extends Controller
{
    use AuthorizesRequests;

    public function index(Form $form): JsonResponse
    {
        $this->authorize('view', $form);

        return response()->json([
            'data' => FormFieldResource::collection($form->fields()->ordered()->get()),
        ]);
    }

    public function store(StoreFormFieldRequest $request, Form $form): JsonResponse
    {
        $this->authorize('update', $form);

        $field = $form->fields()->create($request->validated());

        return response()->json([
            'message' => 'Field created successfully',
            'data' => new FormFieldResource($field),
        ], 201);
    }

    public function update(UpdateFormFieldRequest $request, Form $form, string $ulid): JsonResponse
    {
        $this->authorize('update', $form);

        $field = $form->fields()->where('ulid', $ulid)->firstOrFail();

        $field->update($request->validated());

        return response()->json([
            'message' => 'Field updated successfully',
            'data' => new FormFieldResource($field),
        ]);
    }

    public function destroy(Form $form, string $ulid): JsonResponse
    {
        $this->authorize('update', $form);

        $field = $form->fields()->where('ulid', $ulid)->firstOrFail();

        $field->delete();

        return response()->json([
            'message' => 'Field deleted successfully',
        ]);
    }

    public function reorder(Request $request, Form $form): JsonResponse
    {
        $this->authorize('update', $form);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:form_fields,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        foreach ($validated['orders'] as $orderData) {
            $cases[] = 'WHEN id = ? THEN ?::integer';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        \DB::statement(
            "UPDATE form_fields SET order_column = CASE {$casesString} END WHERE id IN ({$idsString}) AND form_id = ?",
            [...$params, $form->id]
        );

        return response()->json([
            'message' => 'Field order updated successfully',
        ]);
    }
}
