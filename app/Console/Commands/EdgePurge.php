<?php

namespace App\Console\Commands;

use App\Support\EdgeCache;
use Illuminate\Console\Command;

/**
 * Emergency valve for the event websites' edge cache.
 *
 * With phase-2 TTLs (detail pages 30 days, lists 7 days) a silently broken
 * purge pipeline could leave stale content up for a long time. This command
 * bypasses the tag machinery entirely and purges whole zones, so recovery
 * never depends on the thing that just failed.
 *
 *   php artisan edge:purge --project=icc   # one project's site(s)
 *   php artisan edge:purge --all           # every site in config/edge-sites.php
 */
class EdgePurge extends Command
{
    protected $signature = 'edge:purge {--project= : PM One project username} {--all : Purge every configured site}';

    protected $description = 'Purge event websites from the Cloudflare edge (purge_everything per zone)';

    public function handle(): int
    {
        if (! EdgeCache::isConfigured()) {
            $this->error('CLOUDFLARE_EDGE_PURGE_TOKEN is not set — nothing can be purged.');

            return self::FAILURE;
        }

        $project = $this->option('project');

        if (! $project && ! $this->option('all')) {
            $this->error('Pass --project=<username> or --all.');

            return self::INVALID;
        }

        $sites = EdgeCache::sitesFor($project ?: null);

        foreach ($sites as $site) {
            EdgeCache::purgeSite($site);
            $this->info("purged: {$site['url']} ({$site['app']})");
        }

        $this->comment(count($sites).' site(s) purged. Pages re-render on next visit; expect a brief CPU bump.');

        return self::SUCCESS;
    }
}
