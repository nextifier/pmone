<?php

namespace App\Jobs;

use App\Support\WorkersBuilds;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Starts a Cloudflare Workers build for one event website.
 *
 * Queued so a bulk "Rebuild selected" returns immediately instead of holding the
 * request open for one HTTP call per site. Unique for a minute because a double
 * click, or two operators reacting to the same content change, should not burn
 * two builds — Cloudflare would queue the second behind the first anyway.
 */
class TriggerWorkerBuild implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public int $backoff = 15;

    public int $uniqueFor = 60;

    public function __construct(public string $worker)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->worker;
    }

    public function handle(): void
    {
        if (! WorkersBuilds::trigger($this->worker)) {
            return;
        }

        // Deploying new static assets does not invalidate the zone's cached
        // HTML, so without this the rebuild is invisible: the operator watches
        // it go green and the site keeps serving the previous build.
        PurgeEdgeAfterBuild::dispatch($this->worker)->delay(now()->addMinute());
    }
}
