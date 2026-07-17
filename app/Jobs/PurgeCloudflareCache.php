<?php

namespace App\Jobs;

use App\Support\CloudflareCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Clears the Cloudflare edge cache after the origin response cache was cleared,
 * so the event websites never serve content the app already considers stale.
 *
 * Deliberately unique + delayed: a single editor action fans out into several
 * ResponseCache::clear() calls (a controller, then an OG-image job), and bulk
 * operations into many more. Without the debounce each one would be its own
 * zone purge.
 */
class PurgeCloudflareCache implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before purging, so a burst of clears collapses into one
     * purge. Sets the floor on how quickly published content reaches the edge.
     */
    public const DEBOUNCE_SECONDS = 10;

    public int $tries = 2;

    public int $timeout = 30;

    public int $backoff = 30;

    /**
     * Ceiling on the unique lock in case the job dies without releasing it.
     * The lock normally clears the moment the purge finishes.
     */
    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        CloudflareCache::purgeEverything();
    }
}
