<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\User\UserActivityAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class UserActivityAnalyticsController extends Controller
{
    public function __construct(private UserActivityAnalyticsService $analytics) {}

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json(['data' => $this->analytics->summary($from, $to)]);
    }

    public function detail(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json(['data' => $this->analytics->detail($from, $to)]);
    }

    public function forUser(Request $request, User $user): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json(['data' => $this->analytics->forUser($user, $from, $to)]);
    }

    /**
     * Optional window override; absent params keep the service's default
     * 30-day rolling window. Capped at 366 days because the service loads
     * every page view in range into PHP.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : null;
        $to = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : null;

        if ($from !== null && $to !== null && $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'date_to' => 'The date range may not exceed 366 days.',
            ]);
        }

        return [$from, $to];
    }
}
