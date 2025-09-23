<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): JsonResponse
    {
        // Revoke current token
        $request->user()?->currentAccessToken()?->delete();

        // Log activity
        if ($request->user()) {
            activity()
                ->performedOn($request->user())
                ->withProperties(['ip' => $request->ip()])
                ->log('User logged out');
        }

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
