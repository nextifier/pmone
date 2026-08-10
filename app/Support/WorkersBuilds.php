<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
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
     * Recent builds for one Worker, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function history(string $worker, int $perPage = 10): array
    {
        $tag = static::scriptTags()[$worker] ?? null;

        if (! $tag) {
            return [];
        }

        $response = static::request()->get(
            static::url("/builds/workers/{$tag}/builds"),
            ['per_page' => $perPage],
        );

        return static::ok($response, 'build history') ? (array) $response->json('result', []) : [];
    }

    /**
     * Log lines for one build, each with the moment it was written.
     *
     * Cloudflare returns `[unixTimestamp, message]` tuples and `truncated` says
     * whether the tail was cut off. The timestamps are what make a downloaded
     * log worth reading — without them there is no way to tell a build that
     * spent four minutes installing from one that spent four minutes hanging.
     *
     * @return array{lines: array<int, array{at: string|null, text: string}>, truncated: bool}
     */
    public static function logs(string $buildUuid): array
    {
        $response = static::request()->timeout(20)
            ->get(static::url("/builds/builds/{$buildUuid}/logs"));

        if (! static::ok($response, 'build logs')) {
            return ['lines' => [], 'truncated' => false];
        }

        return [
            'lines' => array_map(
                fn ($line) => [
                    'at' => static::logTimestamp($line[0] ?? null),
                    'text' => (string) ($line[1] ?? ''),
                ],
                (array) $response->json('result.lines', []),
            ),
            'truncated' => (bool) $response->json('result.truncated', false),
        ];
    }

    /**
     * A log tuple's timestamp as ISO 8601, or null when it is not one.
     *
     * The endpoint has been seen returning both seconds and milliseconds, so
     * the unit is inferred from the magnitude. Anything that cannot be an epoch
     * yields null and the line still renders, just without a clock.
     */
    protected static function logTimestamp(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        if ($value > 1e12) {
            $value /= 1000;
        }

        if ($value < 1e9) {
            return null;
        }

        return Carbon::createFromTimestamp($value)->toIso8601String();
    }

    /** Stop a build that is still queued or running. */
    public static function cancel(string $buildUuid): bool
    {
        $response = static::request()->put(static::url("/builds/builds/{$buildUuid}/cancel"));

        return static::ok($response, 'cancel build');
    }

    /**
     * Account build-minute limits, so the page can warn before the bill does.
     *
     * @return array{reached: bool, refresh_on: string|null}|null
     */
    public static function accountLimits(): ?array
    {
        if (! static::isConfigured()) {
            return null;
        }

        // Cheap and rarely changing; polled alongside the build list.
        return Cache::remember('workers-builds:limits', 300, function (): ?array {
            $response = static::request()->get(static::url('/builds/account/limits'));

            if (! static::ok($response, 'account limits')) {
                return null;
            }

            return [
                'reached' => (bool) $response->json('result.has_reached_build_minutes_limit', false),
                'refresh_on' => $response->json('result.build_minutes_refresh_on'),
            ];
        });
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

    /**
     * The build trigger that deploys our branch, or null when none matches.
     *
     * A Worker has TWO triggers and picking the wrong one silently breaks the
     * deploy. Cloudflare's defaults are "Deploy default branch"
     * (branch_includes ["main"], deploy command `wrangler --cwd <app>/.output
     * deploy`) and "Deploy non-production branches" (branch_includes ["*"],
     * branch_excludes ["main"], deploy command `wrangler versions upload`).
     * The preview one uploads a version without releasing it, and without the
     * --cwd it cannot even find the bundle: the build succeeds and the deploy
     * fails with "Missing entry-point to Worker script". This method used to
     * take result.0 — the preview trigger — so every rebuild from the dashboard
     * failed that way.
     */
    protected static function triggerUuid(string $worker): ?string
    {
        $tag = static::scriptTags()[$worker] ?? null;

        if (! $tag) {
            return null;
        }

        return Cache::remember(
            "workers-builds:trigger:{$worker}",
            static::LOOKUP_TTL,
            function () use ($tag, $worker): ?string {
                $response = static::request()->get(static::url("/builds/workers/{$tag}/triggers"));

                if (! static::ok($response, 'list triggers')) {
                    return null;
                }

                $branch = config('edge-sites.builds_branch', 'main');
                $triggers = (array) $response->json('result', []);

                // Exact branch match first, then a wildcard trigger that does
                // not exclude our branch. Never one that excludes it.
                foreach ([false, true] as $allowWildcard) {
                    foreach ($triggers as $trigger) {
                        $includes = (array) ($trigger['branch_includes'] ?? []);
                        $excludes = (array) ($trigger['branch_excludes'] ?? []);

                        if (in_array($branch, $excludes, true)) {
                            continue;
                        }
                        if (in_array($branch, $includes, true)
                            || ($allowWildcard && in_array('*', $includes, true))) {
                            return $trigger['trigger_uuid'] ?? null;
                        }
                    }
                }

                // Degenerate case: a single trigger that names no branches at
                // all. There is nothing to pick wrongly, so use it.
                if (count($triggers) === 1
                    && ! in_array($branch, (array) ($triggers[0]['branch_excludes'] ?? []), true)) {
                    return $triggers[0]['trigger_uuid'] ?? null;
                }

                Log::warning('Workers Builds: no trigger deploys this branch', [
                    'worker' => $worker,
                    'branch' => $branch,
                ]);

                return null;
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
