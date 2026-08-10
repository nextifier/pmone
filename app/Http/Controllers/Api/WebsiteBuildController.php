<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\TriggerWorkerBuild;
use App\Models\Project;
use App\Support\ProjectContentActivity;
use App\Support\WorkersBuilds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Build status and manual rebuilds for the event websites.
 *
 * Every public page on those sites is prerendered, so content edited here only
 * reaches visitors on the next Cloudflare Workers build. This is where an
 * operator sees which sites are behind, what they are behind on, and starts the
 * builds that catch them up.
 */
class WebsiteBuildController extends Controller
{
    /** How many recent builds the detail page and its stats are based on. */
    protected const HISTORY_SIZE = 10;

    /**
     * Every configured site with its latest build and whether it is stale.
     *
     * One batched Cloudflare call and one database query cover all sites, so
     * this is cheap enough to poll while a build is running.
     */
    public function index(): JsonResponse
    {
        $sites = WorkersBuilds::sites();
        $builds = WorkersBuilds::latestBuilds();

        $projectNames = Project::query()
            ->whereIn('username', array_filter(array_column($sites, 'project')))
            ->pluck('name', 'username');

        // Co-located sites (cokelatexpo, icf) render another project's content,
        // so staleness has to be measured against the project they READ from.
        // Sites with no project at all (the dashboard, Levenium, Monara) are
        // filtered out: there is no PM One content behind them to go stale.
        $breakdown = ProjectContentActivity::breakdown(array_values(array_unique(
            array_filter(array_map(fn ($site) => $this->contentProject($site), $sites)),
        )));

        $data = array_map(function (array $site) use ($builds, $projectNames, $breakdown): array {
            $build = $this->presentBuild($builds[$site['app']] ?? null);
            $source = $this->contentProject($site);
            $sources = $source ? ($breakdown[$source] ?? []) : [];
            $contentChangedAt = $sources ? max($sources) : null;

            return [
                'worker' => $site['app'],
                'project' => $site['project'],
                'project_name' => $this->displayName($site, $projectNames),
                'data_source' => $site['data_source'],
                'url' => $site['url'],
                'build' => $build,
                'content_changed_at' => $contentChangedAt?->toIso8601String(),
                'needs_rebuild' => $this->needsRebuild($build, $contentChangedAt),
                'content_changes' => $this->contentChanges($build, $sources),
            ];
        }, $sites);

        return response()->json([
            'data' => $data,
            'meta' => [
                'configured' => WorkersBuilds::isConfigured(),
                'limits' => WorkersBuilds::accountLimits(),
            ],
        ]);
    }

    /**
     * The PM One project whose content this site renders, or null when it
     * renders none (the dashboard, Levenium, Monara).
     *
     * @param  array<string, mixed>  $site
     */
    protected function contentProject(array $site): ?string
    {
        return $site['data_source'] ?: $site['project'];
    }

    /**
     * What to call this site in the UI.
     *
     * Event sites are named after the project that owns them, so the name stays
     * correct when a project is renamed. Sites with no project carry their own
     * `name`, and the worker name is the last resort.
     *
     * @param  array<string, mixed>  $site
     * @param  Collection<string, string>  $projectNames
     */
    protected function displayName(array $site, $projectNames): string
    {
        return $site['name']
            ?? ($site['project'] ? $projectNames[$site['project']] ?? $site['project'] : $site['app']);
    }

    /** One site, with its recent builds, content status and build stats. */
    public function show(string $worker): JsonResponse
    {
        $site = $this->findSite($worker);
        $source = $this->contentProject($site);

        $history = array_map(
            fn (array $build) => $this->presentBuild($build),
            WorkersBuilds::history($worker, static::HISTORY_SIZE),
        );

        $build = $history[0] ?? null;
        $sources = $source ? (ProjectContentActivity::breakdown([$source])[$source] ?? []) : [];
        $contentChangedAt = $sources ? max($sources) : null;

        $projectNames = Project::query()
            ->whereIn('username', array_filter([$site['project']]))
            ->pluck('name', 'username');

        return response()->json([
            'data' => [
                'worker' => $site['app'],
                'project' => $site['project'],
                'project_name' => $this->displayName($site, $projectNames),
                'data_source' => $site['data_source'],
                'content_project' => $source,
                'url' => $site['url'],
                'locales' => $site['locales'] ?? [],
                'build' => $build,
                'content_changed_at' => $contentChangedAt?->toIso8601String(),
                'needs_rebuild' => $this->needsRebuild($build, $contentChangedAt),
                'content_changes' => $this->contentChanges($build, $sources),
                'stats' => $this->stats($history),
                'history' => $history,
            ],
            'meta' => [
                'configured' => WorkersBuilds::isConfigured(),
                'limits' => WorkersBuilds::accountLimits(),
            ],
        ]);
    }

    /**
     * The individual edits waiting on a rebuild.
     *
     * Deliberately free of Cloudflare: this is what a hover card asks for, and
     * making it wait on the builds API would put a network round trip behind a
     * mouse movement. The caller passes `since` — the moment the site last
     * published — from the build data it already holds.
     */
    public function changes(Request $request, string $worker): JsonResponse
    {
        $site = $this->findSite($worker);
        $source = $this->contentProject($site);

        $validated = $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $since = isset($validated['since']) ? Carbon::parse($validated['since']) : null;

        $items = $source ? ProjectContentActivity::items($source, $since) : [];

        return response()->json([
            'data' => [
                'worker' => $site['app'],
                'project' => $source,
                'items' => array_map(fn (array $item) => [
                    ...$item,
                    'changed_at' => $item['changed_at']->toIso8601String(),
                ], $items),
            ],
        ]);
    }

    /** Log lines for one build of one site. */
    public function logs(string $worker, string $buildUuid): JsonResponse
    {
        $this->findSite($worker);

        return response()->json(['data' => WorkersBuilds::logs($buildUuid)]);
    }

    /** Stop a build that is still queued or running. */
    public function cancel(string $worker, string $buildUuid): JsonResponse
    {
        $this->findSite($worker);

        if (! WorkersBuilds::cancel($buildUuid)) {
            return response()->json(['message' => 'Could not cancel that build.'], 502);
        }

        return response()->json(['message' => 'Build cancelled.']);
    }

    /**
     * Queue a rebuild for one or more sites.
     *
     * Cloudflare queues builds past the account's concurrency limit rather than
     * rejecting them, so selecting every site is a supported thing to do.
     */
    public function rebuild(Request $request): JsonResponse
    {
        $known = array_column(WorkersBuilds::sites(), 'app');

        $validated = $request->validate([
            'workers' => ['required', 'array', 'min:1'],
            'workers.*' => ['required', 'string', 'in:'.implode(',', $known)],
        ], [
            'workers.required' => 'Select at least one website to rebuild.',
            'workers.*.in' => 'Unknown website.',
        ]);

        if (! WorkersBuilds::isConfigured()) {
            return response()->json([
                'message' => 'Cloudflare Workers Builds is not configured on this server.',
            ], 503);
        }

        $workers = array_values(array_unique($validated['workers']));

        foreach ($workers as $worker) {
            TriggerWorkerBuild::dispatch($worker);
        }

        return response()->json([
            'message' => count($workers) === 1
                ? 'Rebuild queued.'
                : count($workers).' rebuilds queued.',
            'data' => ['workers' => $workers],
        ]);
    }

    /**
     * @return array{app: string, project: string|null, name?: string, data_source: string|null, url: string, locales?: array}
     */
    protected function findSite(string $worker): array
    {
        $site = collect(WorkersBuilds::sites())->firstWhere('app', $worker);

        abort_if($site === null, 404, 'Unknown website.');

        return $site;
    }

    /**
     * Flatten Cloudflare's build shape into what the dashboard renders.
     *
     * Cloudflare splits build state across two fields: `status` is the lifecycle
     * and only a stopped build carries an outcome — and a failure reads "fail",
     * not "failed".
     *
     * @return array<string, mixed>|null
     */
    protected function presentBuild(?array $build): ?array
    {
        if (! $build) {
            return null;
        }

        $meta = $build['build_trigger_metadata'] ?? [];

        return [
            'uuid' => $build['build_uuid'] ?? null,
            'status' => $build['status'] ?? null,
            'outcome' => $build['build_outcome'] ?? null,
            'created_at' => $build['created_on'] ?? null,
            'started_at' => $build['running_on'] ?? null,
            'finished_at' => $build['stopped_on'] ?? null,
            // Null while a build is still running; the dashboard counts up from
            // `started_at` itself so the number moves between polls.
            'duration_seconds' => $this->elapsed($build['running_on'] ?? null, $build['stopped_on'] ?? null),
            'queued_seconds' => $this->elapsed($build['created_on'] ?? null, $build['running_on'] ?? null),
            'branch' => $meta['branch'] ?? null,
            'commit_hash' => $meta['commit_hash'] ?? null,
            'commit_message' => $meta['commit_message'] ?? null,
            'author' => $meta['author'] ?? null,
            'trigger_source' => $meta['build_trigger_source'] ?? null,
        ];
    }

    /** Whole seconds between two Cloudflare timestamps, or null if either is missing. */
    protected function elapsed(?string $from, ?string $to): ?int
    {
        if (! $from || ! $to) {
            return null;
        }

        return (int) Carbon::parse($from)->diffInSeconds(Carbon::parse($to), absolute: true);
    }

    /**
     * When this site last actually published something.
     *
     * A failed or in-flight build never shipped anything, so a site whose build
     * failed keeps every pending edit listed until one succeeds.
     */
    protected function shippedAt(?array $build): ?Carbon
    {
        return ($build && $build['status'] === 'stopped' && $build['outcome'] === 'success')
            ? Carbon::parse($build['finished_at'])
            : null;
    }

    /**
     * Whether prerendered content has changed since the last SUCCESSFUL build.
     */
    protected function needsRebuild(?array $build, ?Carbon $contentChangedAt): bool
    {
        if (! $contentChangedAt) {
            return false;
        }

        $shippedAt = $this->shippedAt($build);

        return $shippedAt === null || $contentChangedAt->gt($shippedAt);
    }

    /**
     * The content sources this site has not published yet, newest first.
     *
     * Shares `shippedAt()` with `needsRebuild()` on purpose: if the list and the
     * flag could ever disagree, an operator would learn to trust neither.
     *
     * @param  array<string, Carbon>  $sources
     * @return array<int, array{source: string, label: string, changed_at: string}>
     */
    protected function contentChanges(?array $build, array $sources): array
    {
        $shippedAt = $this->shippedAt($build);

        $changes = [];

        foreach ($sources as $source => $changedAt) {
            if ($shippedAt === null || $changedAt->gt($shippedAt)) {
                $changes[] = [
                    'source' => $source,
                    'label' => ProjectContentActivity::SOURCES[$source] ?? $source,
                    'changed_at' => $changedAt->toIso8601String(),
                ];
            }
        }

        return $changes;
    }

    /**
     * What the recent build history says about this site.
     *
     * The average covers successful builds only. A build that fails twenty
     * seconds in would otherwise halve the number and make the site look twice
     * as fast to build as it is.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    protected function stats(array $history): array
    {
        $stopped = array_values(array_filter($history, fn ($build) => $build['status'] === 'stopped'));
        $succeeded = array_values(array_filter($stopped, fn ($build) => $build['outcome'] === 'success'));

        $durations = array_values(array_filter(
            array_column($succeeded, 'duration_seconds'),
            fn ($seconds) => $seconds !== null,
        ));

        return [
            'builds' => count($history),
            'finished' => count($stopped),
            'succeeded' => count($succeeded),
            'success_rate' => $stopped ? (int) round(count($succeeded) / count($stopped) * 100) : null,
            'avg_duration_seconds' => $durations ? (int) round(array_sum($durations) / count($durations)) : null,
            'last_success_at' => $succeeded[0]['finished_at'] ?? null,
            // Newest first, for the run of status dots on the detail page.
            'outcomes' => array_map(fn ($build) => $build['outcome'] ?? $build['status'], $history),
        ];
    }
}
