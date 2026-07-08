<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectCustomField\StoreProjectCustomFieldRequest;
use App\Http\Requests\ProjectCustomField\UpdateProjectCustomFieldRequest;
use App\Http\Resources\ProjectCustomFieldResource;
use App\Models\CustomField;
use App\Models\Project;
use App\Services\CustomFields\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD for a project's brand fields. Thin adapter over CustomFieldService
 * with context CustomField::CONTEXT_BRAND and the Project as owner; responses
 * keep the legacy ProjectCustomField shape via ProjectCustomFieldResource.
 */
class ProjectCustomFieldController extends Controller
{
    public function __construct(private CustomFieldService $customFields) {}

    /**
     * List all custom fields for a project.
     */
    public function index(string $username): JsonResponse
    {
        $project = $this->resolveProject($username);

        $fields = $this->customFields->list($project, CustomField::CONTEXT_BRAND);

        return response()->json([
            'data' => ProjectCustomFieldResource::collection($fields),
        ]);
    }

    /**
     * Create a new custom field.
     */
    public function store(StoreProjectCustomFieldRequest $request, string $username): JsonResponse
    {
        $project = $this->resolveProject($username);

        $field = $this->customFields->create($project, CustomField::CONTEXT_BRAND, $request->validated());

        return response()->json([
            'message' => 'Custom field created successfully.',
            'data' => new ProjectCustomFieldResource($field),
        ], 201);
    }

    /**
     * Update a custom field definition. The storage key is immutable after
     * create (the service strips it), so label edits never orphan stored
     * brand values.
     */
    public function update(UpdateProjectCustomFieldRequest $request, string $username, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $field = $project->customFields()->findOrFail($id);

        $field = $this->customFields->update($field, $request->validated());

        return response()->json([
            'message' => 'Custom field updated successfully.',
            'data' => new ProjectCustomFieldResource($field->fresh()),
        ]);
    }

    /**
     * Delete a custom field definition.
     */
    public function destroy(string $username, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $field = $project->customFields()->findOrFail($id);

        $this->customFields->delete($field);

        return response()->json([
            'message' => 'Custom field deleted successfully.',
        ]);
    }

    /**
     * Reorder custom fields.
     */
    public function reorder(Request $request, string $username): JsonResponse
    {
        $project = $this->resolveProject($username);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $this->customFields->reorder($project, CustomField::CONTEXT_BRAND, $validated['orders']);

        return response()->json([
            'message' => 'Custom field order updated successfully.',
        ]);
    }

    private function resolveProject(string $username): Project
    {
        return Project::query()->where('username', $username)->firstOrFail();
    }
}
