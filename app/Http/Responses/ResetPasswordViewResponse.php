<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\ResetPasswordViewResponse as ResetPasswordViewResponseContract;

class ResetPasswordViewResponse implements ResetPasswordViewResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => 'Please reset password via API',
            'token' => $request->route('token'),
            'email' => $request->email,
        ], 200);
    }
}
