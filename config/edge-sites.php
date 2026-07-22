<?php

/*
|--------------------------------------------------------------------------
| Event websites behind the Cloudflare edge cache
|--------------------------------------------------------------------------
|
| Each pmone-events app renders its pages inside a Cloudflare Worker and stores
| the result in the Cloudflare Cache API (see the pmone-events repo,
| layers/base/server/middleware/00.edge-cache.ts). A Worker runs BEFORE
| Cloudflare's cache, so a zone Cache Rule cannot cache those responses and the
| Worker caches them itself. Those entries live in the ordinary zone cache, which
| means this app can drop them with a normal purge-by-URL call.
|
| Without a purge, an edit made here would not surface until the TTL lapses
| (1 hour for HTML). The purge is the delivery mechanism; the TTL is the net.
|
| `project` is the PM One project that owns the site. `data_source` is the
| project it actually reads content from when that differs — see
| pmOneFetch (`dataSourceUsername || projectUsername`) in the events repo.
| cokelatexpo and icf both render content belonging to `cbe`, so a change to cbe
| has to invalidate three sites, not one. A change is matched against BOTH keys.
|
| Adding a site: append a row. Zone IDs are resolved from the hostname at
| runtime, so there is nothing else to keep in sync.
|
*/

return [

    /*
    | Account-owned API token with `Zone → Cache Purge` and `Zone → Read`,
    | scoped to "All zones from an account" so new zones are covered without
    | touching this config. See docs/cloudflare-edge-cache-token.md.
    |
    | Unset (local, CI) makes every purge a silent no-op.
    */
    'token' => env('CLOUDFLARE_EDGE_PURGE_TOKEN'),

    /*
    | How long to remember the hostname -> zone id lookup. Zones change rarely;
    | this only exists to keep purges from making an extra API round trip.
    */
    'zone_cache_ttl' => 86400,

    /*
    | Locale prefixes are appended to every HTML path ("" = default locale,
    | which carries no prefix under i18n `prefix_except_default`).
    |
    | Every site here is purgeable as of 23 Jul 2026: askindo.id was moved into
    | this Cloudflare account and global-ai-expo moved off *.pages.dev to
    | ai.pmone.id, which closed the last two gaps.
    |
    | If a hostname ever lands on a zone this token cannot reach, EdgeCache skips
    | it with a log line rather than failing the whole purge — that site would
    | then silently fall back to its TTL, which for detail pages is SEVEN DAYS.
    | Check the logs for "unmapped"/"zone" warnings after adding a domain.
    */
    'sites' => [
        ['app' => 'cafeexpo',        'project' => 'cbe',          'data_source' => null,  'url' => 'https://cafebrasserieexpo.com', 'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'campx',           'project' => 'campx',        'data_source' => null,  'url' => 'https://campx.id',              'locales' => ['en']],
        ['app' => 'cokelatexpo',     'project' => 'cei',          'data_source' => 'cbe', 'url' => 'https://cokelatexpo.id',        'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'flei',            'project' => 'flei',         'data_source' => null,  'url' => 'https://franchise-expo.co.id',  'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'global-ai-expo',  'project' => 'globalaiexpo',  'data_source' => null,  'url' => 'https://ai.pmone.id', 'locales' => ['en', 'id']],
        ['app' => 'icc',             'project' => 'icc',          'data_source' => null,  'url' => 'https://indonesiacomiccon.com', 'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'icf',             'project' => 'icf',          'data_source' => 'cbe', 'url' => 'https://indocoffeefestival.com', 'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'iicc',            'project' => 'askindo',      'data_source' => null,  'url' => 'https://iicc.askindo.id',       'locales' => ['en', 'id']],
        ['app' => 'inacon',          'project' => 'inacon',       'data_source' => null,  'url' => 'https://indonesiaanimecon.com', 'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'keramika',        'project' => 'keramika',     'data_source' => null,  'url' => 'https://keramika.co.id',        'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'megabuild',       'project' => 'megabuild',    'data_source' => null,  'url' => 'https://megabuild.co.id',       'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'morefood',        'project' => 'morefood',     'data_source' => null,  'url' => 'https://morefoodexpo.com',      'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'outingexpo',      'project' => 'ioe',          'data_source' => null,  'url' => 'https://indooutingexpo.co.id',  'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
        ['app' => 'panorama-events', 'project' => 'pe',           'data_source' => null,  'url' => 'https://panoramaevents.id',     'locales' => ['en']],
        ['app' => 'panorama-media',  'project' => 'pm',           'data_source' => null,  'url' => 'https://panoramamedia.co.id',   'locales' => ['en']],
        ['app' => 'renex',           'project' => 'renex',        'data_source' => null,  'url' => 'https://renex.megabuild.co.id', 'locales' => ['en', 'id', 'zh', 'ja', 'ko']],
    ],

    /*
    | Response-cache tag -> paths to drop from the edge.
    |
    | `api` paths are absolute and locale-agnostic. `html` paths are expanded
    | across every locale a site declares ("/news" -> "/news", "/id/news", ...).
    |
    | Cloudflare's cache key includes the query string, so a query-varied API
    | endpoint (?locale, ?page, ?placement) has one entry per variant and a
    | bare-path purge misses the variants. That is why HTML paths matter most:
    | they are what a visitor actually loads, and the client re-fetches the API
    | after the shell is served.
    |
    | `global` tags describe changes that touch every page (site settings,
    | appearance, nav, copy) — those purge the whole zone instead, since
    | enumerating every URL would be worse than a full purge.
    */
    'tags' => [
        'brands' => [
            'api' => ['/api/exhibitors', '/api/exhibitors/with-conjunctions', '/api/editions'],
            'html' => ['/brands'],
        ],
        'promotion-posts' => [
            'api' => ['/api/exhibitors'],
            'html' => ['/brands'],
        ],
        'guests' => [
            'api' => ['/api/event/guests'],
            'html' => ['/guests'],
        ],
        'tickets' => [
            'api' => [],
            'html' => ['/tickets'],
        ],
        'faqs' => [
            'api' => ['/api/event/faq'],
            'html' => ['/faq'],
        ],
        'partners' => [
            'api' => ['/api/event/partners'],
            'html' => ['/partners'],
        ],
        'programs' => [
            'api' => ['/api/event/programs'],
            'html' => ['/programs'],
        ],
        'media-coverages' => [
            'api' => ['/api/event/media-coverage'],
            'html' => ['/partners'],
        ],
        'blog-posts' => [
            'api' => ['/api/blog/posts'],
            'html' => ['/news'],
        ],
        'hotels' => [
            'api' => ['/api/hotels'],
            'html' => ['/hotels'],
        ],
        'exchange-rates' => [
            'api' => ['/api/hotels'],
            'html' => ['/hotels'],
        ],
        'forms-public' => [
            'api' => [],
            'html' => [],
        ],
        'events' => [
            'api' => ['/api/event/active', '/api/editions', '/api/event/rundown'],
            'html' => ['/rundown', '/gallery', '/programs'],
        ],
        // SINGULAR. The models emit 'rundown', not 'rundowns' — an earlier
        // version of this file spelled it plural, so every rundown edit purged
        // nothing at all. Verify a tag exists in the code before adding it here:
        //   grep -rho "ResponseCache::clear(\[[^]]*\])" app/
        'rundown' => [
            'api' => ['/api/event/rundown'],
            'html' => ['/rundown'],
        ],
        'gallery' => [
            'api' => ['/api/event/gallery'],
            'html' => ['/gallery'],
        ],
        'banners' => [
            'api' => ['/api/banners'],
            'html' => ['/'],
        ],
        // Brand.php emits both 'brands' and, in places, the singular form.
        'brand' => [
            'api' => ['/api/exhibitors', '/api/editions'],
            'html' => ['/brands'],
        ],
        'short-links' => [
            'api' => [],
            'html' => ['/links'],
        ],
    ],

    /*
    | Tags whose blast radius is the entire site. A settings/appearance/copy
    | change alters the header, footer and meta of every page, so there is no
    | useful URL list to build.
    */
    'global_tags' => [
        'projects',
        'website-copy',
        'website-pages',
        // Site settings drive the header, footer, nav, appearance and section
        // toggles on every page — there is no useful URL subset.
        'website-settings',
        // Custom-field/validation changes reshape every public form shell.
        'validation',
    ],
];
