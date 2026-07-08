<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventDocumentField\StoreEventDocumentFieldRequest;
use App\Http\Requests\EventDocumentField\UpdateEventDocumentFieldRequest;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\Project;
use App\Services\CustomFields\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sub-resource CRUD for a document's mini-form fields. Thin adapter over
 * CustomFieldService with context CustomField::CONTEXT_DOCUMENT and the
 * EventDocument as owner.
 */
class EventDocumentFieldController extends Controller
{
    public function __construct(private CustomFieldService $customFields) {}

    public function index(string $username, string $eventSlug, string $documentUlid): JsonResponse
    {
        $document = $this->resolveDocument($username, $eventSlug, $documentUlid);

        $fields = $this->customFields->list($document, CustomField::CONTEXT_DOCUMENT);

        return response()->json([
            'data' => CustomFieldResource::collection($fields),
            'meta' => ['total' => $fields->count()],
        ]);
    }

    public function store(StoreEventDocumentFieldRequest $request, string $username, string $eventSlug, string $documentUlid): JsonResponse
    {
        $document = $this->resolveDocument($username, $eventSlug, $documentUlid);

        $field = $this->customFields->create($document, CustomField::CONTEXT_DOCUMENT, $request->validated());

        return response()->json([
            'message' => 'Field created successfully',
            'data' => new CustomFieldResource($field),
        ], 201);
    }

    public function update(UpdateEventDocumentFieldRequest $request, string $username, string $eventSlug, string $documentUlid, string $fieldUlid): JsonResponse
    {
        $document = $this->resolveDocument($username, $eventSlug, $documentUlid);
        $field = $this->resolveField($document, $fieldUlid);

        $field = $this->customFields->update($field, $request->validated());

        return response()->json([
            'message' => 'Field updated successfully',
            'data' => new CustomFieldResource($field->fresh()),
        ]);
    }

    public function destroy(string $username, string $eventSlug, string $documentUlid, string $fieldUlid): JsonResponse
    {
        $document = $this->resolveDocument($username, $eventSlug, $documentUlid);
        $field = $this->resolveField($document, $fieldUlid);

        $this->customFields->delete($field);

        return response()->json(['message' => 'Field deleted successfully']);
    }

    public function reorder(Request $request, string $username, string $eventSlug, string $documentUlid): JsonResponse
    {
        $document = $this->resolveDocument($username, $eventSlug, $documentUlid);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $this->customFields->reorder($document, CustomField::CONTEXT_DOCUMENT, $validated['orders']);

        return response()->json(['message' => 'Field order updated successfully']);
    }

    private function resolveDocument(string $username, string $eventSlug, string $documentUlid): EventDocument
    {
        $project = Project::query()->where('username', $username)->firstOrFail();

        /** @var Event $event */
        $event = $project->events()->where('slug', $eventSlug)->firstOrFail();

        return $event->eventDocuments()->where('ulid', $documentUlid)->firstOrFail();
    }

    /**
     * The field must belong to this document's mini-form; anything else is a
     * 404 (anti-IDOR, mirrors the manual fieldable assert used elsewhere).
     */
    private function resolveField(EventDocument $document, string $fieldUlid): CustomField
    {
        return $document->fields()->where('ulid', $fieldUlid)->firstOrFail();
    }
}
