<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalAuth
{
    /**
     * Handle an incoming request.
     * Attempts to authenticate the user but doesn't fail if unauthenticated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Attempt to authenticate using Sanctum guard
        // This will check for Bearer token or session cookie
        // If no valid auth found, request continues as guest
        try {
            if ($user = Auth::guard('sanctum')->user()) {
                Auth::setUser($user);
            }
        } catch (\Exception $e) {
            // Silently continue as guest if authentication fails
        }

        return $next($request);
    }
}
