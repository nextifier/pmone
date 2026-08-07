<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads and triggers Cloudflare Workers Builds for the event websites.
 *
 * Each pmone-events app is its own Worker, built from the shared GitHub repo by
 * Cloudflare's CI. Almost every public page is prerendered, so content edited in
 * this dashboard only reaches a site on its next build — which is what the
 * Event Websites page exists to trigger and monitor.
 *
 * Two Cloudflare concepts share confusingly similar names:
 *  - a BUILD is a CI run and is the only thing that can report queued/running/
 *    failed, which is exactly what an operator needs to see;
 *  - a DEPLOYMENT is a released version, and only exists once a build succeeded.
 * Everything here talks to the builds API.
 *
 * Nothing throws. A Cloudflare hiccup means the page shows "unknown" for a row
 * or a rebuild silently does not start — worth a log line, never worth a 500.
 */
class WorkersBuilds
{
    protected const API = 'https://api.cloudflare.com/client/v4';

    /** Worker name -> script tag, and tag -> trigger uuid. Both are immutable per Worker. */
    protected const LOOKUP_TTL = 86400;

    /** The batch endpoint accepts at most 20 script ids per call. */
    protected const LATEST_BATCH = 20;

    public static function isConfigured(): bool
    {
        return filled(config('edge-sites.builds_token'))
            && filled(config('services.cloudflare.account_id'));
    }

    /**
     * The event-website Workers this dashboard knows about.
     *
     * @return array<int, array{app: string, project: string, data_source: string|null, url: string}>
     */
    public static function sites(): array
    {
        return array_values(config('edge-sites.sites', []));
    }

    /**
     * Latest build per Worker, keyed by Worker name.
     *
     * @param  string[]  $workers  Worker names; defaults to every configured site.
     * @return array<string, array<string, mixed>>
     */
    public static function latestBuilds(array $workers = []): array
    {
        if (! static::isConfigured()) {
            return [];
        }

        $workers = $workers ?: array_column(static::sites(), 'app');
        $tags = static::scriptTags();

        // tag -> worker, so the response (keyed by script id) can be mapped back.
        $byTag = [];
        foreach ($workers as $worker) {
            if ($tag = $tags[$worker] ?? null) {
                $byTag[$tag] = $worker;
            }
        }

        $builds = [];

        foreach (array_chunk(array_keys($byTag), static::LATEST_BATCH) as $chunk) {
            $response = static::request()->get(static::url('/builds/builds/latest'), [
                'external_script_ids' => implode(',', $chunk),
            ]);

            if (! static::ok($response, 'latest builds')) {
                continue;
            }

            foreach ((array) $response->json('result.builds', []) as $tag => $build) {
                if ($worker = $byTag[$tag] ?? null) {
                    $builds[$worker] = $build;
                }
            }
        }

        return $builds;
    }

    /**
     * Start a build of the configured branch for one Worker.
     *
     * Cloudflare queues rather than rejects when the account's concurrent-build
     * limit is reached, so triggering several at once is safe.
     */
    public static function trigger(string $worker): bool
    {
        if (! static::isConfigured()) {
            return false;
        }

        $triggerUuid = static::triggerUuid($worker);

        if (! $triggerUuid) {
            Log::warning('Workers Builds: no trigger for worker', ['worker' => $worker]);

            return false;
        }

        $response = static::request()->post(
            static::url("/builds/triggers/{$triggerUuid}/builds"),
            ['branch' => config('edge-sites.builds_branch', 'main')],
        );

        if (! static::ok($response, 'trigger build')) {
            return false;
        }

        Log::info('Workers Builds: build triggered', [
            'worker' => $worker,
            'build_uuid' => $response->json('result.build_uuid'),
        ]);

        return true;
    }

    /**
     * Worker name -> immutable script tag.
     *
     * The builds API keys everything by tag, never by name, so this lookup is
     * unavoidable. Tags never change, hence the day-long cache.
     *
     * @return array<string, string>
     */
    public static function scriptTags(): array
    {
        return Cache::remember('workers-builds:tags', static::LOOKUP_TTL, function (): array {
            $response = static::request()->get(static::url('/workers/scripts'));

            if (! static::ok($response, 'list scripts')) {
                return [];
            }

            $tags = [];
            foreach ((array) $response->json('result', []) as $script) {
                if (! empty($script['id']) && ! empty($script['tag'])) {
                    $tags[$script['id']] = $script['tag'];
                }
            }

            return $tags;
        });
    }

    /** Production build trigger for a Worker, or null when it has none connected. */
    protected static function triggerUuid(string $worker): ?string
    {
        $tag = static::scriptTags()[$worker] ?? null;

        if (! $tag) {
            return null;
        }

        return Cache::remember(
            "workers-builds:trigger:{$worker}",
            static::LOOKUP_TTL,
            function () use ($tag): ?string {
                $response = static::request()->get(static::url("/builds/workers/{$tag}/triggers"));

                if (! static::ok($response, 'list triggers')) {
                    return null;
                }

                return $response->json('result.0.trigger_uuid');
            },
        );
    }

    /** Drop the memoised name/tag/trigger lookups, e.g. after adding a Worker. */
    public static function forgetLookups(): void
    {
        Cache::forget('workers-builds:tags');

        foreach (array_column(static::sites(), 'app') as $worker) {
            Cache::forget("workers-builds:trigger:{$worker}");
        }
    }

    protected static function request(): PendingRequest
    {
        return Http::withToken(config('edge-sites.builds_token'))
            ->timeout(10)
            ->connectTimeout(5)
            ->acceptJson();
    }

    protected static function url(string $path): string
    {
        return static::API.'/accounts/'.config('services.cloudflare.account_id').$path;
    }

    protected static function ok(mixed $response, string $what): bool
    {
        if ($response->successful() && (bool) $response->json('success', false)) {
            return true;
        }

        Log::warning('Workers Builds request rejected', [
            'what' => $what,
            'status' => $response->status(),
            'errors' => $response->json('errors'),
        ]);

        return false;
    }
}
