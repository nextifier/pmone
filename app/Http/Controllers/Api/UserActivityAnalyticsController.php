<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\User\UserActivityAnalyticsService;
use Illuminate\Http\JsonResponse;

class UserActivityAnalyticsController extends Controller
{
    public function __construct(private UserActivityAnalyticsService $analytics) {}

    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->analytics->summary()]);
    }

    public function detail(): JsonResponse
    {
        return response()->json(['data' => $this->analytics->detail()]);
    }

    public function forUser(User $user): JsonResponse
    {
        return response()->json(['data' => $this->analytics->forUser($user)]);
    }
}
