<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reservation\ReservationAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EventReservationAnalyticsController extends Controller
{
    public function __construct(private ReservationAnalyticsService $analytics) {}

    public function summary(Request $request, Event $event): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json(['data' => $this->analytics->summary($event, $from, $to)]);
    }

    public function detail(Request $request, Event $event): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json(['data' => $this->analytics->detail($event, $from, $to)]);
    }

    /**
     * Optional narrowing window. Absent params keep the full event history —
     * the dashboards have always been all-time.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return [
            isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : null,
            isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : null,
        ];
    }
}
