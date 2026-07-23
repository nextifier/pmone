<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Drops cached pages and API responses from the Cloudflare edge for the public
 * event websites, so an edit made in this dashboard is visible immediately
 * instead of after the edge TTL lapses.
 *
 * Distinct from App\Support\CloudflareCache, which purges the single
 * `api.pmone.id` zone. This class works across every event zone and purges
 * individual URLs rather than everything, because the event sites cache their
 * rendered HTML: a blanket purge would force every page on a site to re-render
 * at once, which is exactly the Workers CPU cost this whole effort removes.
 *
 * Nothing here ever throws. A failed purge means content is stale until its TTL
 * expires — worth a log line, never worth failing the editor's save.
 */
class EdgeCache
{
    /** Cloudflare caps purge-by-URL at 30 URLs per request on non-Enterprise plans. */
    protected const URLS_PER_REQUEST = 30;

    public static function isConfigured(): bool
    {
        return filled(config('edge-sites.token'));
    }

    /**
     * Purge everything the given response-cache tags can affect.
     *
     * @param  string[]  $tags  Spatie response-cache tags, e.g. ['brands'].
     * @param  string[]  $extraPaths  Locale-less HTML paths known precisely from
     *                                the changed model, e.g. ['/news/my-post'].
     *                                These are what make a detail page update
     *                                instantly — a tag alone cannot name a slug.
     * @param  string|null  $project  PM One project username that changed. Null
     *                                means "unknown", which fans out to every
     *                                site — stale content is a bug, an extra
     *                                purge is only a little wasted work.
     */
    public static function purgeTags(array $tags, array $extraPaths = [], ?string $project = null): void
    {
        if (! static::isConfigured() || empty($tags)) {
            return;
        }

        $sites = static::sitesFor($project);
        $globalTags = (array) config('edge-sites.global_tags', []);
        $tagMap = (array) config('edge-sites.tags', []);

        // FAIL SAFE. A tag nobody mapped would otherwise resolve to an empty URL
        // list and purge NOTHING — silently, with no error anywhere. That is how
        // 'rundown' went unpurged for a while: this file spelled it 'rundowns'.
        // An unknown tag is now treated as site-wide, so the failure mode of
        // forgetting to map something is a little wasted work instead of content
        // that never updates. Add the tag to `tags` to make it precise again.
        $unmapped = array_values(array_filter(
            $tags,
            fn ($tag) => ! isset($tagMap[$tag]) && ! in_array($tag, $globalTags, true),
        ));

        if ($unmapped !== []) {
            Log::info('Edge purge: unmapped cache tag, falling back to full purge', [
                'tags' => $unmapped,
            ]);
        }

        // A settings/appearance/copy change rewrites the header and footer of
        // every page, so there is no URL list worth building.
        if ($unmapped !== [] || array_intersect($tags, $globalTags)) {
            foreach ($sites as $site) {
                static::purgeSite($site);
            }

            return;
        }

        $urls = [];

        foreach ($sites as $site) {
            $locales = $site['locales'] ?? ['en'];
            $base = rtrim($site['url'], '/');

            foreach ($tags as $tag) {
                $paths = $tagMap[$tag] ?? null;
                if (! $paths) {
                    continue;
                }

                // The worker caches API responses under their REAL query string
                // (?locale=en …), and purge-by-URL matches exactly — a bare-path
                // purge hits nothing the frontend actually requests. Locale is
                // the one enumerable dimension; unenumerable ones (?page,
                // ?placement…) are bounded by the short API TTL instead.
                foreach ($paths['api'] ?? [] as $path) {
                    $urls[] = $base.$path;
                    foreach ($locales as $locale) {
                        $urls[] = $base.$path.'?locale='.$locale;
                    }
                }

                // HTML entries are keyed with the ?__cm colour-mode variant the
                // worker adds (see buildEdgeCacheKey in the events repo). Purging
                // the bare URL alone matches NOTHING — this exact mistake made
                // every dashboard purge a silent no-op on 23 Jul 2026. Keep the
                // suffixes in lockstep with the worker's key scheme.
                foreach (array_merge($paths['html'] ?? [], $extraPaths) as $path) {
                    if ($path === '/') {
                        // The homepage is negotiated (locale cookie +
                        // Accept-Language), so its key space is bigger — but
                        // deliberately finite. See homeVariantUrls().
                        array_push($urls, ...static::homeVariantUrls($base, $locales));

                        continue;
                    }
                    foreach (static::localeVariants($path, $locales) as $variant) {
                        $urls[] = $base.$variant.'?__cm=dark';
                        $urls[] = $base.$variant.'?__cm=light';
                    }
                }
            }
        }

        static::purgeUrls(array_values(array_unique($urls)));
    }

    /**
     * Every cache-key variant the worker can store for a site's bare "/".
     *
     * The worker keys "/" on its locale-negotiation inputs, deliberately
     * collapsed to stay enumerable (see buildEdgeCacheKey in the events repo —
     * KEEP IN LOCKSTEP):
     *   __lc = locale cookie clamped to the site's locales, else "none"
     *   __al = first Accept-Language match; "-" when a cookie exists
     *          (i18n gives the cookie precedence); "none"/"other" otherwise
     *   __cm = dark|light
     * Locale-prefixed homepages ("/id" …) go through the normal path variants;
     * this list is only for the negotiated bare "/".
     *
     * @return string[]
     */
    public static function homeVariantUrls(string $base, array $locales): array
    {
        $urls = [];

        $alWithoutCookie = array_values(array_unique(['none', 'other', ...$locales]));

        foreach (['dark', 'light'] as $cm) {
            // Bot requests skip the negotiation inputs entirely (they collapse
            // onto the default variant), so their key is just ?__cm=….
            $urls[] = $base.'/?__cm='.$cm;

            foreach ($alWithoutCookie as $al) {
                $urls[] = $base.'/?__cm='.$cm.'&__lc=none&__al='.$al;
            }
            foreach ($locales as $lc) {
                $urls[] = $base.'/?__cm='.$cm.'&__lc='.$lc.'&__al=-';
            }
        }

        return $urls;
    }

    /**
     * Expand an HTML path across a site's locales. Under i18n's
     * `prefix_except_default` the default locale carries no prefix, so "/news"
     * has to be purged as both "/news" and "/id/news", "/zh/news", ...
     *
     * @return string[]
     */
    protected static function localeVariants(string $path, array $locales): array
    {
        $variants = [$path];

        foreach ($locales as $locale) {
            if ($locale === 'en') {
                continue; // default locale — already covered by the bare path
            }
            $variants[] = '/'.$locale.($path === '/' ? '' : $path);
        }

        return $variants;
    }

    /**
     * Sites affected by a change in the given project. Matches BOTH `project`
     * and `data_source`: cokelatexpo and icf render content owned by `cbe`, so
     * editing cbe must invalidate all three sites.
     *
     * @return array<int, array>
     */
    public static function sitesFor(?string $project): array
    {
        $sites = (array) config('edge-sites.sites', []);

        if ($project === null) {
            return $sites;
        }

        $matched = array_values(array_filter(
            $sites,
            fn ($site) => $site['project'] === $project || ($site['data_source'] ?? null) === $project,
        ));

        // An unrecognised project must not silently purge nothing — better to
        // over-purge than to serve content the dashboard already changed.
        return $matched ?: $sites;
    }

    /**
     * Purge specific URLs, grouped per zone and chunked to Cloudflare's limit.
     *
     * @param  string[]  $urls
     */
    public static function purgeUrls(array $urls): void
    {
        if (! static::isConfigured() || empty($urls)) {
            return;
        }

        $byZone = [];

        foreach ($urls as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if (! $host) {
                continue;
            }

            $zoneId = static::zoneForHost($host);
            if (! $zoneId) {
                // Expected for iicc.askindo.id, whose zone lives in another
                // Cloudflare account. That site falls back to its TTL.
                continue;
            }

            $byZone[$zoneId][] = $url;
        }

        foreach ($byZone as $zoneId => $zoneUrls) {
            foreach (array_chunk($zoneUrls, self::URLS_PER_REQUEST) as $chunk) {
                static::call($zoneId, ['files' => $chunk]);
            }
        }
    }

    /** Purge an entire site's zone. Used only for global tags. */
    public static function purgeSite(array $site): void
    {
        $host = parse_url($site['url'], PHP_URL_HOST);
        $zoneId = $host ? static::zoneForHost($host) : null;

        if ($zoneId) {
            static::call($zoneId, ['purge_everything' => true]);
        }
    }

    /**
     * Resolve a hostname to its zone id by longest-suffix match against the
     * zones this token can see. Doing it dynamically (rather than storing zone
     * ids in config) is what lets a newly added website work with no config
     * change beyond its row in `edge-sites.sites`.
     */
    public static function zoneForHost(string $host): ?string
    {
        $zoneId = static::matchZone($host, static::zones());

        if ($zoneId !== null) {
            return $zoneId;
        }

        // A miss usually means the zone list is stale: the domain was added to
        // Cloudflare after the list was cached, and until the cache expired the
        // site would silently never be purged. Refresh once and retry, so adding
        // a domain needs no cache-clearing ritual. A genuinely unknown host just
        // costs one extra API call, and only on the first purge that mentions it.
        Cache::forget('edge-cache:zones');

        return static::matchZone($host, static::zones());
    }

    /** Longest-suffix match of a hostname against zone names. */
    protected static function matchZone(string $host, array $zones): ?string
    {
        $best = null;
        $bestLength = 0;

        foreach ($zones as $name => $id) {
            if ($host === $name || str_ends_with($host, '.'.$name)) {
                if (strlen($name) > $bestLength) {
                    $best = $id;
                    $bestLength = strlen($name);
                }
            }
        }

        return $best;
    }

    /**
     * zone name => zone id for every zone the token can reach.
     *
     * @return array<string, string>
     */
    protected static function zones(): array
    {
        return Cache::remember('edge-cache:zones', (int) config('edge-sites.zone_cache_ttl', 86400), function () {
            $zones = [];
            $page = 1;

            do {
                try {
                    $response = Http::withToken(config('edge-sites.token'))
                        ->timeout(10)
                        ->connectTimeout(5)
                        ->get('https://api.cloudflare.com/client/v4/zones', [
                            'per_page' => 50,
                            'page' => $page,
                        ]);
                } catch (\Throwable $e) {
                    Log::warning('Cloudflare zone listing failed', ['error' => $e->getMessage()]);

                    return $zones;
                }

                if (! $response->successful() || ! $response->json('success')) {
                    Log::warning('Cloudflare zone listing rejected', ['status' => $response->status()]);

                    return $zones;
                }

                foreach ($response->json('result', []) as $zone) {
                    $zones[$zone['name']] = $zone['id'];
                }

                $totalPages = (int) $response->json('result_info.total_pages', 1);
                $page++;
            } while ($page <= $totalPages);

            return $zones;
        });
    }

    /** One purge request. Never throws; a failure only means stale content. */
    protected static function call(string $zoneId, array $payload): bool
    {
        try {
            $response = Http::withToken(config('edge-sites.token'))
                ->timeout(10)
                ->connectTimeout(5)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", $payload);

            if ($response->successful() && (bool) $response->json('success', false)) {
                return true;
            }

            Log::warning('Cloudflare edge purge rejected', [
                'zone' => $zoneId,
                'status' => $response->status(),
                'errors' => $response->json('errors'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cloudflare edge purge failed', [
                'zone' => $zoneId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
