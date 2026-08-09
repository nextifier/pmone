<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Widens CORS to `Access-Control-Allow-Origin: *` for the public read surface
 * and the tracking beacons, and only for those.
 *
 * WHY THIS EXISTS AND WHY IT CANNOT BE config/cors.php
 *
 * `config/cors.php` keeps an exact origin allowlist with
 * `supports_credentials: true`, which the Sanctum SPA at pmone.id depends on.
 * A wildcard is illegal alongside credentials, so the two surfaces cannot share
 * one config — hence the per-request narrowing here rather than an edit there.
 *
 * WHY A WILDCARD RATHER THAN LISTING THE SIXTEEN EVENT DOMAINS
 *
 * These responses are edge-cached. Cloudflare honours no `Vary` header except
 * `Accept-Encoding`, so a per-origin `Access-Control-Allow-Origin` baked into a
 * cache entry would be replayed to the fifteen other sites, and every one of
 * them would fail the browser's origin check. One shared entry can only be
 * correct if the header it carries is correct for everyone.
 *
 * `CorsService::configureAllowedOrigin()` takes its `allowAllOrigins` branch
 * only when credentials are off. That branch has two properties this depends
 * on: it sets the header even when the request carries no `Origin` at all (the
 * prerenderer, curl, the crawler that populates the cache), and it skips
 * `varyHeader($response, 'Origin')`. Both are required for a cached copy to be
 * usable by the next caller.
 *
 * Dropping credentials costs nothing here: none of these endpoints reads a
 * cookie or a session, and the browser sends no `X-API-Key` — a keyless GET is
 * a CORS-simple request, which is the point. No custom header means no
 * preflight, so a read is one round trip instead of two.
 *
 * SCOPE
 *
 * READ_PATHS must mirror exactly the route groups carrying `api.key:optional`
 * in routes/api.php — no wider. `api/public/*` as a blanket would sweep in
 * `ticket-orders/{ulid}` and `attendees/{ulid}`, which are GET, cacheable by
 * method, and return one buyer's order. Their API key would still stop a
 * cross-origin read, but a wildcard there buys nothing and widens the blast
 * radius of any future mistake for no reason.
 *
 * PublicApiCorsScopeTest asserts the two sets are identical by walking the
 * route table, so adding a route to one and forgetting the other fails CI.
 *
 * Plus `POST api/track/*`: the websites send those as a `sendBeacon` with a
 * form-urlencoded body, simple for the same reason and for the same benefit.
 *
 * OCTANE
 *
 * The `config()` mutation is per-request under PHP-FPM, which is what this app
 * runs. Under Octane the container is reused between requests and this would
 * leak into the next one. If Octane is ever adopted, replace this with a
 * HandleCors subclass bound for these paths.
 */
class PublicApiCorsScope
{
    /**
     * Cacheable reads the event websites fetch from the visitor's browser.
     *
     * @var list<string>
     */
    public const READ_PATHS = [
        'api/public/blog/*',
        'api/public/banners',
        'api/public/hotels',
        'api/public/events/*',
        'api/public/projects/*',
    ];

    /**
     * Carved back out of READ_PATHS: the Form Builder embeds sit under the
     * projects prefix but keep a mandatory key and per-origin CORS.
     *
     * @var list<string>
     */
    public const READ_PATH_EXCEPTIONS = [
        'api/public/projects/*/forms/*',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isTrackBeacon = $request->isMethod('POST') && $request->is('api/track/*');

        if (self::isBrowserRead($request) || $isTrackBeacon) {
            // HandleCors reads these inside its own handle(), so mutating them
            // here — before it runs — is what selects the wildcard branch.
            config([
                'cors.allowed_origins' => ['*'],
                'cors.allowed_origins_patterns' => [],
                'cors.supports_credentials' => false,
            ]);
        }

        return $next($request);
    }

    /**
     * Whether this request is one of the browser-facing public reads.
     *
     * Public and static so the drift test can call it with a synthetic request
     * built from each route's URI, rather than restating the patterns.
     */
    public static function isBrowserRead(Request $request): bool
    {
        if (! $request->isMethodCacheable()) {
            return false;
        }

        return $request->is(...self::READ_PATHS)
            && ! $request->is(...self::READ_PATH_EXCEPTIONS);
    }
}
