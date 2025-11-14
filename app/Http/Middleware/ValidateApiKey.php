<?php

namespace App\Http\Middleware;

use App\Models\ApiConsumer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header or query parameter
        $apiKey = $request->header('X-API-Key') ?? $request->query('api_key');

        if (! $apiKey) {
            return response()->json([
                'message' => 'API key is required',
                'error' => 'Missing API key in X-API-Key header or api_key query parameter',
            ], 401);
        }

        // Find and validate API consumer
        $consumer = ApiConsumer::byApiKey($apiKey)->active()->first();

        if (! $consumer) {
            return response()->json([
                'message' => 'Invalid or inactive API key',
                'error' => 'The provided API key is not valid or has been deactivated',
            ], 401);
        }

        // Check CORS origin if specified
        $origin = $request->header('Origin');
        if ($origin && ! $consumer->isOriginAllowed($origin)) {
            return response()->json([
                'message' => 'Origin not allowed',
                'error' => 'Your domain is not authorized to use this API key',
            ], 403);
        }

        // Apply rate limiting per consumer
        $rateLimitKey = 'api-consumer:'.$consumer->id;
        $maxAttempts = $consumer->rate_limit;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Too many requests',
                'error' => 'Rate limit exceeded. Please try again in '.$seconds.' seconds.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 60); // 60 seconds window

        // Attach consumer to request for later use
        $request->attributes->set('api_consumer', $consumer);

        // Update last used timestamp (async to avoid blocking)
        dispatch(function () use ($consumer) {
            $consumer->markAsUsed();
        })->afterResponse();

        return $next($request);
    }
}
