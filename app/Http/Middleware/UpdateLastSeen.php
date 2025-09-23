<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Update last_seen only for authenticated users
        if (Auth::check()) {
            $user = Auth::user();

            // Only update if it's been more than 1 minute since last update to avoid too many DB hits
            if (! $user->last_seen || $user->last_seen->lt(now()->subMinute())) {
                $user->markAsOnline();
            }
        }

        return $next($request);
    }
}
