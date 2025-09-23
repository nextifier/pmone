<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse as RequestPasswordResetLinkViewResponseContract;

class RequestPasswordResetLinkViewResponse implements RequestPasswordResetLinkViewResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => 'Please reset password via API',
            'reset_url' => route('password.request'),
        ], 200);
    }
}
