<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse
    {
        $user = $request->user();

        // Mark user as logged in
        $user->markAsLoggedIn($request->ip());

        // Create token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        // Log activity
        activity()
            ->performedOn($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('User logged in');

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'token' => $token,
            'expires_at' => now()->addDays(30)->toISOString(),
        ]);
    }
}
