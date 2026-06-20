<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventDay\StoreEventDayRequest;
use App\Http\Requests\EventDay\UpdateEventDayRequest;
use App\Http\Resources\EventDayResource;
use App\Models\Event;
use App\Models\EventDay;
use App\Services\Ticket\EventDayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventDayController extends Controller
{
    public function index(Event $event): JsonResponse
    {
        $days = $event->eventDays()->orderBy('order_column')->get();

        return response()->json([
            'data' => EventDayResource::collection($days),
            'meta' => ['total' => $days->count()],
        ]);
    }

    public function store(StoreEventDayRequest $request, Event $event): JsonResponse
    {
        $day = $event->eventDays()->create($request->validated());

        return response()->json([
            'message' => 'Event day created successfully',
            'data' => new EventDayResource($day),
        ], 201);
    }

    public function show(Event $event, EventDay $eventDay): JsonResponse
    {
        return response()->json(['data' => new EventDayResource($eventDay)]);
    }

    public function update(UpdateEventDayRequest $request, Event $event, EventDay $eventDay): JsonResponse
    {
        $eventDay->update($request->validated());

        return response()->json([
            'message' => 'Event day updated successfully',
            'data' => new EventDayResource($eventDay->fresh()),
        ]);
    }

    public function destroy(Event $event, EventDay $eventDay): JsonResponse
    {
        $eventDay->delete();

        return response()->json(['message' => 'Event day deleted successfully']);
    }

    public function reorder(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        if ($event->eventDays()->whereIn('id', $ids)->count() !== count($ids)) {
            return response()->json(['message' => 'One or more days do not belong to this event.'], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $order) {
                $event->eventDays()->where('id', $order['id'])->update(['order_column' => $order['order']]);
            }
        });

        return response()->json(['message' => 'Event day order updated successfully']);
    }

    /**
     * Derive the event days from the event's start_date..end_date range. Idempotent
     * and id-stable (existing days are matched on date; out-of-range days are
     * deactivated, never deleted) so tickets that reference a day keep working.
     */
    public function sync(Event $event, EventDayService $service): JsonResponse
    {
        $days = $service->syncFromEventDates($event);

        return response()->json([
            'message' => 'Event days synced from the event date range.',
            'data' => EventDayResource::collection($days),
            'meta' => ['total' => $days->count()],
        ]);
    }

    /**
     * Set exactly the supplied day ids active (the rest inactive). Backs the
     * Event Days ToggleGroup in Ticket Settings.
     */
    public function setActive(Request $request, Event $event, EventDayService $service): JsonResponse
    {
        $validated = $request->validate([
            'active_ids' => ['present', 'array'],
            'active_ids.*' => ['integer'],
        ]);

        $days = $service->setActiveDays($event, $validated['active_ids']);

        return response()->json([
            'message' => 'Event days updated.',
            'data' => EventDayResource::collection($days),
        ]);
    }
}
