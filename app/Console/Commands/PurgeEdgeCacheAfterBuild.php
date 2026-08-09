<?php

namespace App\Console\Commands;

use App\Support\EdgeCache;
use App\Support\WorkersBuilds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Drop an event site's edge cache once its Worker has been redeployed.
 *
 * WHY THIS IS NECESSARY. The sites cache their rendered HTML at the Cloudflare
 * edge, and that HTML embeds hashed `/_nuxt/*` chunk URLs. A deploy replaces
 * those chunks, so HTML from the previous build points at files that no longer
 * exist — the iicc white-screen incident.
 *
 * The worker guards against that itself: every stored entry carries an
 * `x-edge-build` header, and a mismatch is treated as a miss
 * (pmone-events/layers/base/server/middleware/00.edge-cache.ts). That guard is
 * enough ONLY while every request reaches the worker. It no longer does.
 * Measured 9 Aug 2026: once the responses stopped carrying `Set-Cookie` and
 * started carrying a public `s-maxage`, Cloudflare's own CDN began storing them
 * and answering repeat requests without invoking the worker at all — 20
 * consecutive requests to one article, all `cf-cache-status: HIT`. A response
 * served by the CDN never runs the build check, and deploying a Worker does not
 * purge the zone. So the stale-chunk window is now bounded by the edge TTL
 * rather than by the guard, and article TTL is 30 days.
 *
 * Hence this: watch for a new successful build and purge that site's zone.
 * Cloudflare Workers Builds has no webhook, so it is polled.
 *
 * NOT a duplicate of App\Jobs\PurgeEdgeAfterBuild. That job is dispatched by
 * TriggerWorkerBuild, so it only ever covers a rebuild started from the
 * dashboard — and these sites are almost always deployed by a git push, which
 * that job never sees. It purges within about half a minute where it applies;
 * this sweeps everything else within five. Both write SEEN_KEY, so a build
 * handled by one is not purged again by the other.
 *
 * Purging everything (rather than a URL list) is the point — after a deploy
 * every page on the site is potentially stale, and there is no enumerable list
 * of article URLs to name. The assets it also drops are served free by the
 * Assets binding and simply re-warm.
 */
class PurgeEdgeCacheAfterBuild extends Command
{
    protected $signature = 'edge-cache:purge-after-build
                            {--dry-run : Report what would be purged without purging}';

    protected $description = "Purge an event site's edge cache when its Worker has been redeployed";

    /**
     * Remembered per worker so only a CHANGED build triggers a purge. Public
     * because App\Jobs\PurgeEdgeAfterBuild writes the same marker after a
     * dashboard-triggered rebuild.
     */
    public const SEEN_KEY = 'edge-cache:last-built:';

    public function handle(): int
    {
        if (! WorkersBuilds::isConfigured() || ! EdgeCache::isConfigured()) {
            $this->components->warn('Workers Builds or edge purge is not configured — nothing to do.');

            return self::SUCCESS;
        }

        // Keyed by worker name, which is exactly `edge-sites.sites[].app`.
        $sites = collect(WorkersBuilds::sites())->keyBy('app');
        $builds = WorkersBuilds::latestBuilds();

        if ($builds === []) {
            $this->components->warn('Cloudflare returned no builds.');

            return self::SUCCESS;
        }

        $purged = 0;

        foreach ($builds as $worker => $build) {
            // "stopped" is terminal but neutral — a cancelled or failed build
            // stops too, and neither replaced the deployed assets.
            if (($build['status'] ?? null) !== 'stopped' || ($build['build_outcome'] ?? null) !== 'success') {
                continue;
            }

            $uuid = $build['build_uuid'] ?? null;
            $site = $sites[$worker] ?? null;

            if (! $uuid || ! $site) {
                continue;
            }

            $key = self::SEEN_KEY.$worker;

            if (Cache::get($key) === $uuid) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->components->info("Would purge {$worker} ({$site['url']}) for build {$uuid}");

                continue;
            }

            EdgeCache::purgeSite($site);
            // Written even if the purge silently failed: EdgeCache never throws,
            // and retrying a purge every five minutes forever would be worse
            // than one stale site. The next build gets its own chance.
            Cache::forever($key, $uuid);
            $purged++;

            $this->components->info("Purged {$worker} ({$site['url']}) after build {$uuid}");
        }

        if ($purged > 0) {
            Log::info('Edge cache purged after new Worker builds', ['sites' => $purged]);
        }

        return self::SUCCESS;
    }
}
