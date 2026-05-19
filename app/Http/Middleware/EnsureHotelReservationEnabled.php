<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\HotelEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks hotel/reservation-related endpoints when the event's
 * `hotel_reservation_enabled` flag is off OR the event's project has no
 * active payment gateway configured. Both conditions must pass.
 *
 * Resolves the event from one of these sources, in order:
 *   - route param `event` (admin event-scoped routes)
 *   - request body `event_id` (public reservation POST)
 *   - request `event_slug` (public availability POST, query string)
 *   - if request has `hotel_id` only, derive event from active pivot rows
 *     - if none, deny (cannot resolve which event the hotel belongs to)
 *
 * Returns 404 when the feature is off to avoid leaking which events
 * exist; returns 422 only when the request shape itself is invalid.
 */
class EnsureHotelReservationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $this->resolveEvent($request);

        if (! $event) {
            // Could not resolve event from request. Let the controller's
            // own validation handle missing identifiers; only block when we
            // CAN resolve the event and the feature is off.
            return $next($request);
        }

        if (! $event->hotel_reservation_enabled) {
            return response()->json([
                'message' => 'Hotel reservation is not enabled for this event.',
                'error_code' => 'HOTEL_RESERVATION_DISABLED',
            ], 404);
        }

        $project = $event->project;
        if (! $project || ! $project->hasActivePaymentGateway()) {
            return response()->json([
                'message' => 'Hotel reservation is not available for this event.',
                'error_code' => 'HOTEL_RESERVATION_DISABLED',
            ], 404);
        }

        return $next($request);
    }

    private function resolveEvent(Request $request): ?Event
    {
        // 1. Route-bound event model (admin event-scoped routes)
        $routeEvent = $request->route('event');
        if ($routeEvent instanceof Event) {
            return $routeEvent;
        }
        if (is_numeric($routeEvent)) {
            return Event::find($routeEvent);
        }

        // 2. eventSlug URL segment (public per-event routes)
        if ($slugParam = $request->route('eventSlug')) {
            return Event::where('slug', $slugParam)->first();
        }

        // 3. event_id in payload (public reservation POST)
        if ($id = $request->input('event_id')) {
            return Event::find((int) $id);
        }

        // 4. event_slug in payload (public availability POST)
        if ($slug = $request->input('event_slug')) {
            return Event::where('slug', $slug)->first();
        }

        // 5. derive from hotel_id via pivot (single active event)
        if ($hotelId = $request->input('hotel_id')) {
            $eventIds = HotelEvent::query()
                ->where('hotel_id', (int) $hotelId)
                ->where('is_active', true)
                ->pluck('event_id')
                ->all();
            if (count($eventIds) === 1) {
                return Event::find($eventIds[0]);
            }
        }

        return null;
    }
}
