<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reservation\ReservationAnalyticsService;
use Illuminate\Http\JsonResponse;

class EventReservationAnalyticsController extends Controller
{
    public function __construct(private ReservationAnalyticsService $analytics) {}

    public function summary(Event $event): JsonResponse
    {
        return response()->json(['data' => $this->analytics->summary($event)]);
    }

    public function detail(Event $event): JsonResponse
    {
        return response()->json(['data' => $this->analytics->detail($event)]);
    }
}
