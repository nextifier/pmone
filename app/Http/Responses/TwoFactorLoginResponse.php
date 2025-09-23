<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => 'Two factor authentication required',
            'two_factor_challenge_url' => route('two-factor.login'),
        ], 200);
    }
}
