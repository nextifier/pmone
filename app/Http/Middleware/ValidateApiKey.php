<?php

namespace App\Http\Middleware;

use App\Models\ApiConsumer;
use App\Models\ApiConsumerRequest;
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

        // Apply rate limiting per consumer (skip if rate_limit is 0 = unlimited)
        if ($consumer->rate_limit > 0) {
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
        }

        // Attach consumer to request for later use
        $request->attributes->set('api_consumer', $consumer);

        // Track request start time
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Calculate response time
        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        // Extract serializable data before dispatching
        $consumerId = $consumer->id;
        $endpoint = $request->path();
        $method = $request->method();
        $statusCode = $response->getStatusCode();
        $ipAddress = $request->ip();
        $userAgent = substr($request->userAgent() ?? '', 0, 255);
        $origin = $request->header('Origin');

        // Log request and update last used timestamp (async to avoid blocking)
        // Use only primitive values to avoid PDO serialization issues
        app()->terminating(function () use ($consumerId, $endpoint, $method, $statusCode, $responseTimeMs, $ipAddress, $userAgent, $origin) {
            ApiConsumer::where('id', $consumerId)->update(['last_used_at' => now()]);

            ApiConsumerRequest::create([
                'api_consumer_id' => $consumerId,
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTimeMs,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'origin' => $origin,
            ]);
        });

        return $response;
    }
}
