<?php

namespace App\Jobs;

use App\Support\EdgeCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drops the affected URLs from the event websites' Cloudflare edge cache after
 * content changed here.
 *
 * Unique + delayed for the same reason as PurgeCloudflareCache: one editor
 * action fans out into several ResponseCache::clear() calls, and a bulk import
 * into hundreds. The uniqueness key covers the actual payload, so two different
 * changes still purge separately while a burst of identical ones collapses into
 * a single request.
 */
class PurgeEdgeCache implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before purging. Sets the floor on how quickly a published
     * change reaches visitors; keep it small — the whole point is immediacy.
     */
    public const DEBOUNCE_SECONDS = 5;

    public int $tries = 2;

    public int $timeout = 60;

    public int $backoff = 15;

    /** Ceiling on the unique lock in case the job dies without releasing it. */
    public int $uniqueFor = 300;

    /**
     * @param  string[]  $tags
     * @param  string[]  $extraPaths
     */
    public function __construct(
        public array $tags,
        public array $extraPaths = [],
        public ?string $project = null,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return md5(json_encode([
            array_values(array_unique($this->tags)),
            array_values(array_unique($this->extraPaths)),
            $this->project,
        ]));
    }

    public function handle(): void
    {
        EdgeCache::purgeTags($this->tags, $this->extraPaths, $this->project);
    }
}
