<?php

namespace App\Providers;

use App\Http\Middleware\TenantCacheResponse;
use App\Models\ContactFormSubmission;
use App\Models\CustomField;
use App\Models\LinkPageItem;
use App\Models\Project;
use App\Models\ShortLink;
use App\Observers\ContactFormSubmissionObserver;
use App\Observers\LinkPageItemObserver;
use App\Observers\ProjectObserver;
use App\Observers\ShortLinkObserver;
use App\Rules\AllowedEmailDomain;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Spatie binds only its own CacheResponse middleware as a singleton.
        // The middleware carries pending cache state from handle() to
        // terminate() on the instance, so the subclass MUST be a singleton
        // too - a fresh instance at terminate() time would silently never
        // store anything.
        $this->app->singleton(TenantCacheResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Translatable fallback chain: requested locale -> en -> any filled
        // locale. Posts are authored Indonesian-first (stored under "id"), so
        // without fallbackAny an id-only record would render empty on the
        // English-default public sites.
        Translatable::fallback(fallbackLocale: 'en', fallbackAny: true);

        // Every email rule in the app resolves through Email::default(). In
        // production an address must actually be able to receive mail (MX
        // record, no reserved/disposable domains), which stops fake signups
        // from ever being sent to - each such send is a guaranteed bounce that
        // drags down the whole account's sender reputation. Outside production
        // the network-dependent DNS check and the domain blocklist are skipped:
        // tests run offline and factories generate @example.com addresses.
        Email::defaults(fn (): Email => app()->isProduction()
            ? Rule::email()->rfcCompliant(strict: true)->preventSpoofing()->validateMxRecord()->rules([new AllowedEmailDomain])
            : Rule::email()->rfcCompliant(strict: true)->preventSpoofing());

        // Activity-log rows written before the centralized custom-fields
        // migration still carry subject_type App\Models\ProjectCustomField;
        // that model was replaced by CustomField, so map the retired FQCN to
        // keep those morphs resolvable. CustomField's own FQCN is registered
        // first so CustomField::getMorphClass() keeps returning it.
        Relation::morphMap([
            CustomField::class => CustomField::class,
            'App\\Models\\ProjectCustomField' => CustomField::class,
        ]);

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

        // Public form submissions are anonymous writes; keep them tight per IP.
        // Uploads get their own (larger) bucket so a multi-file form does not
        // exhaust the submit allowance before the final submit happens.
        RateLimiter::for('form-submit', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('form-upload', function (Request $request) {
            return Limit::perMinute(40)->by($request->ip());
        });

        // Public contact-form / registration submissions (contact, media-partner, sponsorship,
        // exhibitor) all hit one shared endpoint. They are anonymous writes, so throttle tight
        // per real client IP (forwarded by the Cloudflare edge proxy) with a per-hour ceiling
        // on top to blunt slow-drip flooding. Legit users submit once or twice per session.
        RateLimiter::for('contact-submit', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(30)->by($request->ip()),
            ];
        });

        // Access code validation reveals gated tickets — throttle brute-forcing
        // per code+IP, with a per-IP hourly ceiling on top.
        RateLimiter::for('access-code', function (Request $request) {
            return [
                Limit::perMinute(10)->by(strtoupper(trim((string) $request->input('code'))).'|'.$request->ip()),
                Limit::perHour(60)->by($request->ip()),
            ];
        });

        // Presence heartbeat fires on navigation + a 60s keepalive; a per-user
        // ceiling well above that rate absorbs redirect bursts without abuse.
        RateLimiter::for('heartbeat', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
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
