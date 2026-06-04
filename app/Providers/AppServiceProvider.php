<?php

namespace App\Providers;

use App\Models\ContactFormSubmission;
use App\Models\LinkPageItem;
use App\Models\Project;
use App\Models\ShortLink;
use App\Observers\ContactFormSubmissionObserver;
use App\Observers\LinkPageItemObserver;
use App\Observers\ProjectObserver;
use App\Observers\ShortLinkObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure rate limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Public short-link / slug resolution is read-only and high-traffic. Anonymous
        // visitors on mobile carriers share one public IP via carrier-grade NAT, so a
        // tight per-IP limit makes innocent users hit 429. Allow a higher ceiling here.
        RateLimiter::for('short-link', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });

        // SVG/PNG -> SDF conversion is CPU-intensive; throttle tighter than the API.
        RateLimiter::for('sdf-convert', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Register observers
        ContactFormSubmission::observe(ContactFormSubmissionObserver::class);
        Project::observe(ProjectObserver::class);
        ShortLink::observe(ShortLinkObserver::class);
        LinkPageItem::observe(LinkPageItemObserver::class);

        // Global Browsershot defaults for laravel-pdf. Allow Chromium to read
        // local SVG assets referenced via public_path() and give renders a
        // generous timeout. We do NOT wait for network idle — the only assets
        // fetched are inline CSS + a self-hosted woff2 font, both synchronous.
        Pdf::default()->withBrowsershot(function (Browsershot $browsershot) {
            $browsershot
                ->setOption('args', ['--allow-file-access-from-files'])
                ->timeout(60);
        });
    }
}
