<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormFieldRequest;
use App\Http\Requests\UpdateFormFieldRequest;
use App\Http\Resources\FormFieldResource;
use App\Models\CustomField;
use App\Models\Form;
use App\Services\CustomFields\CustomFieldService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormFieldController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CustomFieldService $customFields) {}

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

        $field = $this->customFields->create($form, CustomField::CONTEXT_FORM, $request->validated());

        return response()->json([
            'message' => 'Field created successfully',
            'data' => new FormFieldResource($field),
        ], 201);
    }

    public function update(UpdateFormFieldRequest $request, Form $form, string $ulid): JsonResponse
    {
        $this->authorize('update', $form);

        $field = $form->fields()->where('ulid', $ulid)->firstOrFail();

        $this->customFields->update($field, $request->validated());

        return response()->json([
            'message' => 'Field updated successfully',
            'data' => new FormFieldResource($field->fresh()),
        ]);
    }

    public function destroy(Form $form, string $ulid): JsonResponse
    {
        $this->authorize('update', $form);

        $field = $form->fields()->where('ulid', $ulid)->firstOrFail();

        $this->customFields->delete($field);

        return response()->json([
            'message' => 'Field deleted successfully',
        ]);
    }

    public function reorder(Request $request, Form $form): JsonResponse
    {
        $this->authorize('update', $form);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:custom_fields,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $this->customFields->reorder($form, CustomField::CONTEXT_FORM, $validated['orders']);

        return response()->json([
            'message' => 'Field order updated successfully',
        ]);
    }
}
