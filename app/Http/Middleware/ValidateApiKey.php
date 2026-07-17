<?php

namespace App\Http\Middleware;

use App\Listeners\MarkResponseCacheHit;
use App\Models\ApiConsumer;
use App\Models\ApiConsumerRequest;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header only. The query-string fallback was
        // removed: keys in the query string land in access logs, browser
        // history, and Referer headers, and fragment the response cache.
        // Every known consumer (the 11 event websites) already sends the
        // key via this header.
        $apiKey = $request->header('X-API-Key');

        if (! $apiKey) {
            return response()->json([
                'message' => 'API key is required',
                'error' => 'Missing API key in X-API-Key header',
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

        // Opt-in per-project scope. Unscoped consumers (the default, and
        // every consumer today) are never blocked here. Only a consumer
        // that has been explicitly restricted to specific projects gets
        // checked, and only when the target project is resolvable from the
        // route.
        if ($consumer->hasProjectScope()) {
            $targetUsername = $this->resolveProjectUsername($request);

            if ($targetUsername !== null && ! $consumer->isProjectAllowed($targetUsername)) {
                return response()->json([
                    'message' => 'Project not allowed',
                    'error' => 'This API key is not scoped to access this project',
                ], 403);
            }
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

        // A response served from the response cache did no real origin work,
        // so it is not worth a row. This middleware runs in the api.key group,
        // which is *before* the cacheResponse route middleware, so without the
        // flag set by MarkResponseCacheHit every cache hit would still write —
        // which is how this table reached 17M rows and 3.5GB.
        if ($request->attributes->getBoolean(MarkResponseCacheHit::ATTRIBUTE)) {
            return $response;
        }

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
            $this->touchLastUsedAt($consumerId);

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

    /**
     * Refresh a consumer's last_used_at at most once a minute. The column is
     * only ever read at minute-or-coarser granularity, so writing it on every
     * request bought nothing and churned dead tuples on a hot, tiny table.
     * Mirrors the throttle in UpdateLastSeen.
     */
    private function touchLastUsedAt(int $consumerId): void
    {
        $throttleKey = 'api-consumer-last-used:'.$consumerId;

        if (! Cache::add($throttleKey, true, now()->addMinute())) {
            return;
        }

        ApiConsumer::where('id', $consumerId)->update(['last_used_at' => now()]);
    }

    /**
     * Resolve the project username the current request targets, so scoped
     * consumers can be checked against it. Returns null when it cannot be
     * determined from the route (e.g. the request isn't project-specific),
     * in which case scoping is simply not enforced for that request.
     */
    private function resolveProjectUsername(Request $request): ?string
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return null;
        }

        // Routes keyed directly by a project's username in the path
        // (`public/projects/{username}/...`). The `{username}` param means
        // something different on other prefixes (e.g. blog author), so this
        // is only trusted under the project-scoped route group.
        if ($route->hasParameter('username') && str_starts_with($route->uri(), 'api/public/projects/')) {
            $username = $route->parameter('username');

            return is_string($username) ? $username : null;
        }

        // Routes keyed by an event slug (hotels, tickets, reservations):
        // resolve the event's owning project.
        if ($route->hasParameter('eventSlug')) {
            $event = Event::where('slug', $route->parameter('eventSlug'))
                ->with('project:id,username')
                ->first();

            return $event?->project?->username;
        }

        return null;
    }
}
