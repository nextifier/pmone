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
 * WHY THIS IS NECESSARY. Cached HTML embeds hashed `/_nuxt/*` chunk URLs, and a
 * deploy replaces those chunks — HTML from the previous build points at files
 * that no longer exist, which is the iicc white-screen incident.
 *
 * The two kinds of page on these sites protect themselves very differently, and
 * that difference is the whole reason this command exists.
 *
 * SSR pages (/news/[slug], /brands/[slug]) are rendered by the worker, which
 * stamps `x-edge-build` on every entry it stores and treats a mismatch as a miss
 * (pmone-events/layers/base/server/middleware/00.edge-cache.ts). Verified 9 Aug
 * 2026 on morefood, cokelatexpo and icf: a URL warmed before their rebuild came
 * back `x-edge-cache: STALE-BUILD` afterwards, re-rendered, and every chunk it
 * referenced resolved. That guard works, and this command is not what saves
 * those pages.
 *
 * Prerendered pages are the ones at risk. They are served by the Assets binding
 * without invoking the worker at all, so no guard of ours can run on them —
 * there is no code path to put one in. The failure is on record: 8 Aug 2026,
 * megabuild was redeployed and `/` still answered `cf-cache-status: HIT` with
 * the previous build's body. That is most of the site.
 *
 * Hence this: watch for a new successful build and purge that site's zone.
 * Cloudflare Workers Builds has no webhook, so it is polled.
 *
 * A NOTE ON WHAT CLEARS WHAT. An earlier version of this docblock claimed
 * Cloudflare invalidates the zone on deploy "inconsistently", from watching
 * cached pages disappear after the 10 Aug rebuilds. That reading was wrong:
 * pushing to main auto-deploys this backend, so this command was already live
 * and sweeping every five minutes when those pages were observed — it is the
 * likelier cause. What is still unresolved is that the same rebuilds answered
 * `x-edge-cache: STALE-BUILD`, meaning the worker's own Cache API entry
 * SURVIVED while the CDN copy did not, which a zone-wide purge_everything
 * should not allow if the two are one storage layer. Do not assume either
 * mechanism covers the other until someone measures it properly.
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
