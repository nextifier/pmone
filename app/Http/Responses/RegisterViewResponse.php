<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterViewResponse as RegisterViewResponseContract;

class RegisterViewResponse implements RegisterViewResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => 'Please register via API',
            'register_url' => route('register'),
        ], 401);
    }
}
