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
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add UpdateLastSeen middleware to API routes
        $middleware->api(append: [
            \App\Http\Middleware\UpdateLastSeen::class,
        ]);

        // Exclude tracking endpoints from CSRF verification (for anonymous tracking)
        $middleware->validateCsrfTokens(except: [
            'api/track/*',
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Cleanup temporary uploads older than 1 hour, run hourly
        $schedule->command('uploads:cleanup-temp --hours=1')->hourly();

        // Cleanup tracking data older than 5 years, run daily at 2 AM
        $schedule->command('tracking:cleanup')->dailyAt('02:00');

        // Sync Google Analytics properties that need updating, run every 10 minutes
        $schedule->command('analytics:sync --days=30 --only-needed --queue')->everyTenMinutes();

        // Aggregate analytics data for dashboard, run every 15 minutes
        $schedule->job(new \App\Jobs\AggregateAnalyticsData(null, 30))->everyFifteenMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API requests
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
