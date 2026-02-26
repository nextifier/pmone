<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectCustomFieldController extends Controller
{
    /**
     * List all custom fields for a project.
     */
    public function index(string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        return response()->json([
            'data' => $project->customFields()->ordered()->get(),
        ]);
    }

    /**
     * Create a new custom field.
     */
    public function store(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:text,number,select,year_select,textarea'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $key = Str::snake(Str::ascii($validated['label']));

        // Check for duplicate key in same project
        if ($project->customFields()->where('key', $key)->exists()) {
            return response()->json([
                'message' => 'A custom field with this label already exists.',
            ], 422);
        }

        $field = $project->customFields()->create([
            'label' => $validated['label'],
            'key' => $key,
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
        ]);

        return response()->json([
            'message' => 'Custom field created successfully.',
            'data' => $field,
        ], 201);
    }

    /**
     * Update a custom field definition.
     */
    public function update(Request $request, string $username, int $id): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();
        $field = $project->customFields()->findOrFail($id);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:text,number,select,year_select,textarea'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $newKey = Str::snake(Str::ascii($validated['label']));

        // Check for duplicate key (excluding current field)
        if ($newKey !== $field->key && $project->customFields()->where('key', $newKey)->where('id', '!=', $field->id)->exists()) {
            return response()->json([
                'message' => 'A custom field with this label already exists.',
            ], 422);
        }

        $field->update([
            'label' => $validated['label'],
            'key' => $newKey,
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
        ]);

        return response()->json([
            'message' => 'Custom field updated successfully.',
            'data' => $field,
        ]);
    }

    /**
     * Delete a custom field definition.
     */
    public function destroy(string $username, int $id): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();
        $field = $project->customFields()->findOrFail($id);

        $field->delete();

        return response()->json([
            'message' => 'Custom field deleted successfully.',
        ]);
    }

    /**
     * Reorder custom fields.
     */
    public function reorder(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:project_custom_fields,id'],
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
            "UPDATE project_custom_fields SET order_column = CASE {$casesString} END WHERE id IN ({$idsString}) AND project_id = ?",
            [...$params, $project->id]
        );

        return response()->json([
            'message' => 'Custom field order updated successfully.',
        ]);
    }
}
