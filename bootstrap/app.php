<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // Realtime analytics - refresh every 2 minutes for live user counts
        $schedule->job(new \App\Jobs\RefreshRealtimeAnalytics)->everyTwoMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API requests
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
