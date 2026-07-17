<?php

use App\Http\Middleware\EnsureHotelReservationEnabled;
use App\Http\Middleware\EnsureTicketsEnabled;
use App\Http\Middleware\LogPaymentWebhook;
use App\Http\Middleware\UpdateLastSeen;
use App\Http\Middleware\ValidateApiKey;
use App\Jobs\FetchExchangeRates;
use App\Jobs\SyncTodayAnalyticsJob;
use App\Jobs\WarmAggregateCacheJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\ResponseCache\Middlewares\CacheResponse;
use Spatie\ResponseCache\Middlewares\DoNotCacheResponse;

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
        // Note: HandleCors is part of Laravel's default global middleware stack and
        // does not need to be re-added here. Calling $middleware->use([...]) would
        // REPLACE the entire global stack (dropping TrustProxies, ValidatePathEncoding, etc).

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add UpdateLastSeen middleware to API routes
        $middleware->api(append: [
            UpdateLastSeen::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'api.key' => ValidateApiKey::class,
            'cacheResponse' => CacheResponse::class,
            'doNotCacheResponse' => DoNotCacheResponse::class,
            'hotel-reservation-enabled' => EnsureHotelReservationEnabled::class,
            'tickets-enabled' => EnsureTicketsEnabled::class,
            'log-payment-webhook' => LogPaymentWebhook::class,
        ]);

        // Exclude tracking endpoints from request forgery protection (for anonymous tracking)
        $middleware->preventRequestForgery(except: [
            'api/track/*',
            'api/public/*',
        ]);

        // Trust forwarded headers only from Cloudflare's edge ranges (all public traffic
        // reaches the origin through Cloudflare) plus loopback/private hops for any internal
        // load balancer. This lets $request->ip() resolve the real visitor IP from
        // X-Forwarded-For for analytics + per-IP throttling, while a direct-to-origin request
        // from the public internet can no longer spoof X-Forwarded-For to forge an IP.
        // Cloudflare ranges: https://www.cloudflare.com/ips/ (refresh if Cloudflare changes them).
        $middleware->trustProxies(at: [
            // Cloudflare IPv4
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            // Cloudflare IPv6
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            // Loopback + private ranges (internal load balancer / reverse proxy hops)
            '127.0.0.0/8', '::1/128', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Cleanup temporary uploads older than 1 hour, run hourly
        $schedule->command('uploads:cleanup-temp --hours=1')->hourly();

        // Cleanup orphaned temporary media from content editor, run hourly
        $schedule->command('media:cleanup-temp --hours=24')->hourly();

        // Weekly safety net: prune orphaned media files + records that no longer
        // belong to any model (reclaims disk). Sunday 02:00 - lowest traffic.
        // Catches orphans from cascade deletes / mass deletes that bypass model events.
        $schedule->command('media:prune-orphans --force')
            ->weeklyOn(0, '2:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->environments(['production']);

        // Cleanup tracking data past its retention window (90 days), daily at 2 AM
        $schedule->command('tracking:cleanup')->dailyAt('02:00')->withoutOverlapping();

        // Sync Google Analytics properties - fetch 365 days of daily data for all properties
        // The daily aggregation system will then filter this data for any requested period
        // This runs hourly to keep data fresh while minimizing GA API calls
        $schedule->command('analytics:sync --days=365 --only-needed --queue')->hourly();

        // Sync today's analytics data - runs every 15 minutes for instant loading
        // This proactively fetches today's data so users never have to wait
        // when requesting the "today" period on analytics dashboard
        $schedule->job(new SyncTodayAnalyticsJob)->everyFifteenMinutes();

        // Pre-warm aggregate cache for the dashboard's common ranges (7/30/90 days)
        // so the default view loads real numbers immediately instead of zeros while a
        // background job computes them. Reads cached daily data, no extra GA API calls.
        $schedule->job(new WarmAggregateCacheJob)->everyFifteenMinutes();

        // Realtime analytics is refreshed on demand: AnalyticsService::getRealtimeActiveUsers()
        // already dispatches RefreshRealtimeAnalytics when its cache goes stale and serves the
        // last known good value meanwhile. Refreshing it on a schedule as well meant hitting the
        // GA API for all 13 properties every 2 minutes around the clock (~9k calls/day, 6-9s per
        // run) whether or not anyone had the dashboard open.

        // Fetch exchange rates - interval configurable via EXCHANGE_RATE_SYNC_INTERVAL (default: 60 minutes).
        // The minute field only accepts 0-59, so a step built straight from the interval breaks for
        // any value of 60 or more: "*/60" happens to match minute 0, but "*/90" matches nothing.
        // Anything from an hour up is expressed as an hourly step instead.
        $exchangeRateInterval = max(1, (int) config('services.exchange_rate.sync_interval_minutes', 60));

        if ($exchangeRateInterval < 60) {
            $exchangeRateCron = "*/{$exchangeRateInterval} * * * *";
        } else {
            $exchangeRateHours = max(1, intdiv($exchangeRateInterval, 60));
            $exchangeRateCron = $exchangeRateHours >= 24 ? '0 0 * * *' : "0 */{$exchangeRateHours} * * *";
        }

        $schedule->job(new FetchExchangeRates)->cron($exchangeRateCron);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API requests
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Add CORS headers to API exception responses
        $exceptions->respond(function ($response, $exception, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $allowedOrigins = config('cors.allowed_origins', []);
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
