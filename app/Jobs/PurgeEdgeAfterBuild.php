<?php

namespace App\Jobs;

use App\Support\EdgeCache;
use App\Support\WorkersBuilds;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Waits for one Worker's build to finish, then drops that site from the
 * Cloudflare edge cache.
 *
 * Without this a rebuild is invisible. The zone caches the prerendered HTML, and
 * deploying new static assets does NOT invalidate it — on 8 Aug 2026 megabuild
 * was redeployed and `/` still answered `cf-cache-status: HIT` with the previous
 * build's body, while `/links` (not yet cached) served the new one. An operator
 * clicking Rebuild would watch it go green and see no change.
 *
 * Purging is only correct AFTER the build lands: purge too early and the next
 * visitor re-caches the very page we are trying to replace.
 *
 * Self-rescheduling rather than sleeping, so a slow build never holds a worker.
 */
class PurgeEdgeAfterBuild implements ShouldQueue
{
    use Queueable;

    /** Roughly 20 minutes at 30s intervals — past Cloudflare's own build timeout. */
    protected const MAX_CHECKS = 40;

    protected const CHECK_INTERVAL_SECONDS = 30;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        public string $worker,
        public int $check = 0,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if ($this->check >= static::MAX_CHECKS) {
            Log::warning('Edge purge gave up waiting for build', ['worker' => $this->worker]);

            return;
        }

        $build = WorkersBuilds::latestBuilds([$this->worker])[$this->worker] ?? null;

        // Not stopped yet — or Cloudflare has not registered the new build at
        // all. Either way, check again shortly.
        if (! $build || ($build['status'] ?? null) !== 'stopped') {
            static::dispatch($this->worker, $this->check + 1)
                ->delay(now()->addSeconds(static::CHECK_INTERVAL_SECONDS));

            return;
        }

        if (($build['build_outcome'] ?? null) !== 'success') {
            // A failed build changed nothing, so there is nothing to purge and
            // the cached page is still the correct one.
            Log::info('Edge purge skipped: build did not succeed', [
                'worker' => $this->worker,
                'outcome' => $build['build_outcome'] ?? null,
            ]);

            return;
        }

        $site = collect(WorkersBuilds::sites())->firstWhere('app', $this->worker);

        if (! $site) {
            return;
        }

        EdgeCache::purgeSite($site);

        Log::info('Edge purged after successful build', [
            'worker' => $this->worker,
            'build' => $build['build_uuid'] ?? null,
        ]);
    }
}
