<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginViewResponse as LoginViewResponseContract;

class LoginViewResponse implements LoginViewResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => 'Please authenticate via API',
            'login_url' => route('login'),
        ], 401);
    }
}
