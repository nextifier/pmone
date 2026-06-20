<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks ticketing endpoints when the event's `tickets_enabled` flag is off.
 *
 * Unlike the hotel-reservation gate this checks ONLY the toggle, not an active
 * payment gateway: free and external tickets need no gateway, and the admin
 * config UI must be reachable before any gateway is set. The paid-checkout
 * gateway requirement belongs to the public purchase endpoints (M2).
 *
 * Resolves the event from the route-bound `{event}` (admin), the `eventSlug`
 * URL segment, or `event_id` / `event_slug` in the payload. Returns 404 (not
 * 403) when disabled to avoid leaking which events exist.
 */
class EnsureTicketsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $this->resolveEvent($request);

        if (! $event) {
            // Let the controller's own validation handle a missing identifier;
            // only block when we CAN resolve the event and it's disabled.
            return $next($request);
        }

        if (! $event->tickets_enabled) {
            return response()->json([
                'message' => 'Tickets are not available for this event.',
                'error_code' => 'TICKETS_DISABLED',
            ], 404);
        }

        return $next($request);
    }

    private function resolveEvent(Request $request): ?Event
    {
        $routeEvent = $request->route('event');
        if ($routeEvent instanceof Event) {
            return $routeEvent;
        }
        if (is_numeric($routeEvent)) {
            return Event::find($routeEvent);
        }

        if ($slugParam = $request->route('eventSlug')) {
            return Event::where('slug', $slugParam)->first();
        }

        if ($id = $request->input('event_id')) {
            return Event::find((int) $id);
        }

        if ($slug = $request->input('event_slug')) {
            return Event::where('slug', $slug)->first();
        }

        return null;
    }
}
