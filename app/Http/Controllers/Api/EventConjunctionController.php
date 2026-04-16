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
                'project_name' => $e->project?->name,
                'project_username' => $e->project?->username,
                'edition_number' => $e->edition_number,
                'start_date' => $e->start_date?->toDateString(),
                'conjunction_label' => $e->pivot->conjunction_label,
                'order_column' => $e->pivot->order_column,
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

        return response()->json(['message' => 'Conjunction event added.'], 201);
    }

    /**
     * Remove a conjunction event (bidirectional).
     */
    public function destroy(string $username, string $eventSlug, int $conjunctionEventId): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        // Remove forward direction
        $event->conjunctionEvents()->detach($conjunctionEventId);

        // Remove reverse direction
        $conjunctionEvent = Event::find($conjunctionEventId);
        $conjunctionEvent?->conjunctionEvents()->detach($event->id);

        ResponseCache::clear(['brands']);

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

        $events = Event::query()
            ->with('project')
            ->whereNotIn('id', $existingIds)
            ->published()
            ->orderBy('title')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'project_name' => $e->project?->name,
                'project_username' => $e->project?->username,
                'edition_number' => $e->edition_number,
                'start_date' => $e->start_date?->toDateString(),
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
