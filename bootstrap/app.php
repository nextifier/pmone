<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Cleanup temporary uploads older than 24 hours, run hourly
        $schedule->job(\App\Jobs\CleanupTemporaryUploads::class)->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
