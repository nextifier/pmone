<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventCustomField\StoreEventCustomFieldRequest;
use App\Http\Requests\EventCustomField\UpdateEventCustomFieldRequest;
use App\Http\Resources\EventCustomFieldResource;
use App\Models\Event;
use App\Models\EventCustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventCustomFieldController extends Controller
{
    public function index(Event $event): JsonResponse
    {
        $fields = $event->eventCustomFields()->orderBy('order_column')->get();

        return response()->json([
            'data' => EventCustomFieldResource::collection($fields),
            'meta' => ['total' => $fields->count()],
        ]);
    }

    public function store(StoreEventCustomFieldRequest $request, Event $event): JsonResponse
    {
        $field = $event->eventCustomFields()->create($request->validated());

        return response()->json([
            'message' => 'Business matching field created successfully',
            'data' => new EventCustomFieldResource($field),
        ], 201);
    }

    public function show(Event $event, EventCustomField $customField): JsonResponse
    {
        return response()->json(['data' => new EventCustomFieldResource($customField)]);
    }

    public function update(UpdateEventCustomFieldRequest $request, Event $event, EventCustomField $customField): JsonResponse
    {
        $customField->update($request->validated());

        return response()->json([
            'message' => 'Field updated successfully',
            'data' => new EventCustomFieldResource($customField->fresh()),
        ]);
    }

    public function destroy(Event $event, EventCustomField $customField): JsonResponse
    {
        $customField->delete();

        return response()->json(['message' => 'Field deleted successfully']);
    }

    public function reorder(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();
        if ($event->eventCustomFields()->whereIn('id', $ids)->count() !== count($ids)) {
            return response()->json(['message' => 'One or more fields do not belong to this event.'], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $order) {
                $event->eventCustomFields()->where('id', $order['id'])->update(['order_column' => $order['order']]);
            }
        });

        return response()->json(['message' => 'Field order updated successfully']);
    }
}
