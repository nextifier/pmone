<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\ResponseCache\Facades\ResponseCache;

class EventConjunctionController extends Controller
{
    /**
     * List conjunction events for an event.
     */
    public function index(string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $conjunctionEvents = $event->conjunctionEvents()
            ->with(['project', 'media'])
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'date_label' => $e->date_label,
                'location' => $e->location,
                'conjunction_label' => $e->pivot->conjunction_label,
                'order_column' => $e->pivot->order_column,
                'poster_image' => $e->getMediaUrls('poster_image'),
            ]);

        return response()->json(['data' => $conjunctionEvents]);
    }

    /**
     * Add a conjunction event (bidirectional).
     */
    public function store(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $validated = $request->validate([
            'conjunction_event_id' => ['required', 'integer', 'exists:events,id'],
            'conjunction_label' => ['nullable', 'string', 'max:255'],
        ]);

        $conjunctionEventId = $validated['conjunction_event_id'];

        if ($conjunctionEventId === $event->id) {
            return response()->json(['message' => 'Cannot add self as conjunction.'], 422);
        }

        if ($event->conjunctionEvents()->where('conjunction_event_id', $conjunctionEventId)->exists()) {
            return response()->json(['message' => 'Conjunction already exists.'], 422);
        }

        // Collect all existing conjunction event IDs before adding the new one
        $existingConjunctionIds = $event->conjunctionEvents()->pluck('events.id');

        $conjunctionEvent = Event::findOrFail($conjunctionEventId);
        $label = $validated['conjunction_label'] ?? null;

        // 1. Link current event ↔ new conjunction event
        $this->attachBidirectional($event, $conjunctionEvent, $label);

        // 2. Link new conjunction event ↔ all existing conjunction events
        foreach ($existingConjunctionIds as $existingId) {
            $existingEvent = Event::find($existingId);
            if ($existingEvent) {
                $this->attachBidirectional($existingEvent, $conjunctionEvent, $label);
            }
        }

        ResponseCache::clear(['brands']);

        activity()
            ->causedBy($request->user())
            ->performedOn($event)
            ->event('event_linked')
            ->withProperties([
                'project_id' => $event->project_id,
                'event_id' => $event->id,
                'linked_event_id' => $conjunctionEvent->id,
                'linked_event_title' => $conjunctionEvent->title,
                'conjunction_label' => $label,
            ])
            ->log("Linked conjunction event: {$conjunctionEvent->title}");

        return response()->json(['message' => 'Conjunction event added.'], 201);
    }

    /**
     * Remove a conjunction event (bidirectional).
     */
    public function destroy(string $username, string $eventSlug, int $conjunctionEventId): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $conjunctionEvent = Event::find($conjunctionEventId);

        // Remove forward direction
        $event->conjunctionEvents()->detach($conjunctionEventId);

        // Remove reverse direction
        $conjunctionEvent?->conjunctionEvents()->detach($event->id);

        ResponseCache::clear(['brands']);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($event)
            ->event('event_unlinked')
            ->withProperties([
                'project_id' => $event->project_id,
                'event_id' => $event->id,
                'linked_event_id' => $conjunctionEventId,
                'linked_event_title' => $conjunctionEvent?->title,
            ])
            ->log('Unlinked conjunction event');

        return response()->json(['message' => 'Conjunction event removed.']);
    }

    /**
     * Reorder conjunction events.
     */
    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:events,id'],
        ]);

        foreach ($validated['order'] as $index => $eventId) {
            $event->conjunctionEvents()->updateExistingPivot($eventId, [
                'order_column' => $index + 1,
            ]);
        }

        ResponseCache::clear(['brands']);

        return response()->json(['message' => 'Order updated.']);
    }

    /**
     * List available events for conjunction (from all projects, excluding current event and already-added).
     */
    public function available(string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $existingIds = $event->conjunctionEvents()->pluck('events.id')->push($event->id);

        // Match the /events page ordering: ongoing first, upcoming (closest to today),
        // completed (most recent first), draft/no-date last.
        $now = now();
        $events = Event::query()
            ->with(['project', 'media'])
            ->whereNotIn('id', $existingIds)
            ->published()
            ->orderByRaw('
                CASE
                    WHEN start_date IS NOT NULL AND start_date <= ? AND (end_date IS NULL OR end_date >= ?) THEN 0
                    WHEN start_date IS NOT NULL AND start_date > ? THEN 1
                    WHEN start_date IS NOT NULL AND (end_date IS NOT NULL AND end_date < ? OR end_date IS NULL AND start_date < ?) THEN 2
                    ELSE 3
                END ASC
            ', [$now, $now->copy()->startOfDay(), $now->copy()->endOfDay(), $now->copy()->startOfDay(), $now->copy()->startOfDay()])
            ->orderByRaw('
                CASE
                    WHEN start_date IS NOT NULL AND start_date > ? THEN ABS(EXTRACT(EPOCH FROM (start_date - ?::timestamp)))
                    WHEN start_date IS NOT NULL THEN -EXTRACT(EPOCH FROM start_date)
                    ELSE 999999999
                END ASC
            ', [$now->copy()->endOfDay(), $now])
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'date_label' => $e->date_label,
                'location' => $e->location,
                'poster_image' => $e->getMediaUrls('poster_image'),
            ]);

        return response()->json(['data' => $events]);
    }

    /**
     * Attach two events as conjunctions bidirectionally (skip if already linked).
     */
    private function attachBidirectional(Event $eventA, Event $eventB, ?string $label = null): void
    {
        if (! $eventA->conjunctionEvents()->where('conjunction_event_id', $eventB->id)->exists()) {
            $maxOrder = $eventA->conjunctionEvents()->max('event_conjunctions.order_column') ?? 0;
            $eventA->conjunctionEvents()->attach($eventB->id, [
                'conjunction_label' => $label,
                'order_column' => $maxOrder + 1,
            ]);
        }

        if (! $eventB->conjunctionEvents()->where('conjunction_event_id', $eventA->id)->exists()) {
            $maxOrder = $eventB->conjunctionEvents()->max('event_conjunctions.order_column') ?? 0;
            $eventB->conjunctionEvents()->attach($eventA->id, [
                'conjunction_label' => $label,
                'order_column' => $maxOrder + 1,
            ]);
        }
    }

    private function findEvent(string $username, string $eventSlug): Event
    {
        $project = Project::query()
            ->where('username', $username)
            ->active()
            ->firstOrFail();

        return Event::query()
            ->where('project_id', $project->id)
            ->where('slug', $eventSlug)
            ->firstOrFail();
    }
}
