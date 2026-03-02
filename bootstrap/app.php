<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Suppress PHP 8.5 deprecation warnings for PDO::MYSQL_ATTR_SSL_CA
// This is a temporary fix until Laravel releases an update
// See: https://github.com/laravel/framework/issues
error_reporting(E_ALL & ~E_DEPRECATED);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Handle CORS for all requests
        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add UpdateLastSeen middleware to API routes
        $middleware->api(append: [
            \App\Http\Middleware\UpdateLastSeen::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'api.key' => \App\Http\Middleware\ValidateApiKey::class,
            'cacheResponse' => \Spatie\ResponseCache\Middlewares\CacheResponse::class,
            'doNotCacheResponse' => \Spatie\ResponseCache\Middlewares\DoNotCacheResponse::class,
        ]);

        // Exclude tracking endpoints from CSRF verification (for anonymous tracking)
        $middleware->validateCsrfTokens(except: [
            'api/track/*',
            'api/public/*',
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Cleanup temporary uploads older than 1 hour, run hourly
        $schedule->command('uploads:cleanup-temp --hours=1')->hourly();

        // Cleanup orphaned temporary media from content editor, run hourly
        $schedule->command('media:cleanup-temp --hours=24')->hourly();

        // Cleanup tracking data older than 5 years, run daily at 2 AM
        $schedule->command('tracking:cleanup')->dailyAt('02:00');

        // Sync Google Analytics properties - fetch 365 days of daily data for all properties
        // The daily aggregation system will then filter this data for any requested period
        // This runs hourly to keep data fresh while minimizing GA API calls
        $schedule->command('analytics:sync --days=365 --only-needed --queue')->hourly();

        // Sync today's analytics data - runs every 15 minutes for instant loading
        // This proactively fetches today's data so users never have to wait
        // when requesting the "today" period on analytics dashboard
        $schedule->job(new \App\Jobs\SyncTodayAnalyticsJob)->everyFifteenMinutes();

        // Realtime analytics - refresh every 2 minutes for live user counts
        $schedule->job(new \App\Jobs\RefreshRealtimeAnalytics)->everyTwoMinutes();

        // Fetch exchange rates - interval configurable via EXCHANGE_RATE_SYNC_INTERVAL (default: 60 minutes)
        $exchangeRateInterval = (int) config('services.exchange_rate.sync_interval_minutes', 60);
        $schedule->job(new \App\Jobs\FetchExchangeRates)->cron("*/{$exchangeRateInterval} * * * *");
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API requests
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Add CORS headers to API exception responses
        $exceptions->respond(function ($response, $exception, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $allowedOrigins = explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://pmone.test'));
                $origin = $request->header('Origin');

                if ($origin && in_array($origin, $allowedOrigins)) {
                    $response->headers->set('Access-Control-Allow-Origin', $origin);
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN');
                }
            }

            return $response;
        });
    })->create();
