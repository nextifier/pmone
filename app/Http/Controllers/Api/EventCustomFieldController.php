<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventCustomField\StoreEventCustomFieldRequest;
use App\Http\Requests\EventCustomField\UpdateEventCustomFieldRequest;
use App\Http\Resources\EventCustomFieldResource;
use App\Models\CustomField;
use App\Models\Event;
use App\Services\CustomFields\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for event-scoped custom fields. Context-aware: business_matching
 * (the historical default of these routes) and ticket_registration share the
 * same endpoints via a `context` parameter, plus the predefined-library
 * toggles. `{customField}` binds by id and is manually asserted against the
 * event because scoped bindings cannot traverse a filtered morph.
 */
class EventCustomFieldController extends Controller
{
    public function __construct(private readonly CustomFieldService $customFields) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $context = $this->contextFrom($request);

        $fields = $this->customFields->list($event, $context);

        return response()->json([
            'data' => EventCustomFieldResource::collection($fields),
            'meta' => ['total' => $fields->count()],
        ]);
    }

    public function store(StoreEventCustomFieldRequest $request, Event $event): JsonResponse
    {
        $field = $this->customFields->create(
            $event,
            (string) $request->validated('context'),
            $request->fieldAttributes(),
        );

        return response()->json([
            'message' => 'Field created successfully',
            'data' => new EventCustomFieldResource($field),
        ], 201);
    }

    public function show(Event $event, CustomField $customField): JsonResponse
    {
        $this->assertBelongsToEvent($event, $customField);

        return response()->json(['data' => new EventCustomFieldResource($customField)]);
    }

    public function update(UpdateEventCustomFieldRequest $request, Event $event, CustomField $customField): JsonResponse
    {
        $this->assertBelongsToEvent($event, $customField);

        $this->customFields->update($customField, $request->fieldAttributes());

        return response()->json([
            'message' => 'Field updated successfully',
            'data' => new EventCustomFieldResource($customField->fresh()),
        ]);
    }

    public function destroy(Event $event, CustomField $customField): JsonResponse
    {
        $this->assertBelongsToEvent($event, $customField);

        $this->customFields->delete($customField);

        return response()->json(['message' => 'Field deleted successfully']);
    }

    public function reorder(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'context' => ['sometimes', Rule::in([
                CustomField::CONTEXT_BUSINESS_MATCHING,
                CustomField::CONTEXT_TICKET_REGISTRATION,
            ])],
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $this->customFields->reorder(
            $event,
            $validated['context'] ?? CustomField::CONTEXT_BUSINESS_MATCHING,
            $validated['orders'],
        );

        return response()->json(['message' => 'Field order updated successfully']);
    }

    public function predefined(Request $request, Event $event): JsonResponse
    {
        return response()->json([
            'data' => $this->customFields->predefinedStatus($event, $this->contextFrom($request)),
        ]);
    }

    public function togglePredefined(Request $request, Event $event, string $systemKey): JsonResponse
    {
        $validated = $request->validate([
            'context' => ['sometimes', Rule::in([
                CustomField::CONTEXT_BUSINESS_MATCHING,
                CustomField::CONTEXT_TICKET_REGISTRATION,
            ])],
            'enabled' => ['required', 'boolean'],
        ]);

        $field = $this->customFields->togglePredefined(
            $event,
            $validated['context'] ?? CustomField::CONTEXT_BUSINESS_MATCHING,
            $systemKey,
            (bool) $validated['enabled'],
        );

        return response()->json([
            'message' => $validated['enabled'] ? 'Field enabled successfully' : 'Field disabled successfully',
            'data' => new EventCustomFieldResource($field),
        ]);
    }

    protected function contextFrom(Request $request): string
    {
        $context = (string) $request->query('context', CustomField::CONTEXT_BUSINESS_MATCHING);

        abort_unless(in_array($context, [
            CustomField::CONTEXT_BUSINESS_MATCHING,
            CustomField::CONTEXT_TICKET_REGISTRATION,
        ], true), 422, 'Invalid context.');

        return $context;
    }

    protected function assertBelongsToEvent(Event $event, CustomField $customField): void
    {
        abort_unless(
            $customField->fieldable_type === Event::class
                && $customField->fieldable_id === $event->id,
            404,
        );
    }
}
