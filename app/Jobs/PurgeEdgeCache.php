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
     * change reaches visitors, so it should stay small — but it has a HARD
     * MINIMUM and 5 s was below it.
     *
     * Every public GET proxy in the events repo is a `defineCachedEventHandler`
     * with `maxAge: 15`, and that cache lives inside the Cloudflare Worker where
     * no purge can reach it. Purging at +5 s therefore dropped the HTML while
     * the worker still held the OLD payload: the next visitor re-rendered the
     * page from it and the stale result was stored as fresh for seven days.
     * That is how a banner CTA edited at 03:38 on 25 Jul 2026 was purged at
     * 03:39:02 and re-cached, still wrong, at 03:39:07.
     *
     * 20 s clears that 15 s window with margin. LOCKSTEP: if the events repo
     * raises maxAge in server/api/**, raise this to match (the reasoning is
     * repeated in layers/base/shared/cf-cache-rules.ts).
     */
    public const DEBOUNCE_SECONDS = 20;

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
