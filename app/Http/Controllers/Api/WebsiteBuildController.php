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
 * operator sees which sites are behind and starts the builds that catch them up.
 */
class WebsiteBuildController extends Controller
{
    /**
     * Every configured site with its latest build and whether it is stale.
     *
     * One batched Cloudflare call covers all sites, so this is cheap enough to
     * poll while a build is running.
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
        $changedAt = ProjectContentActivity::lastChangedAt(array_values(array_unique(
            array_filter(array_map(fn ($site) => $this->contentProject($site), $sites)),
        )));

        $data = array_map(function (array $site) use ($builds, $projectNames, $changedAt): array {
            $build = $this->presentBuild($builds[$site['app']] ?? null);
            $source = $this->contentProject($site);
            $contentChangedAt = $source ? ($changedAt[$source] ?? null) : null;

            return [
                'worker' => $site['app'],
                'project' => $site['project'],
                'project_name' => $this->displayName($site, $projectNames),
                'data_source' => $site['data_source'],
                'url' => $site['url'],
                'build' => $build,
                'content_changed_at' => $contentChangedAt?->toIso8601String(),
                'needs_rebuild' => $this->needsRebuild($build, $contentChangedAt),
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

    /** One site, with its recent build history. */
    public function show(string $worker): JsonResponse
    {
        $site = $this->findSite($worker);

        return response()->json([
            'data' => [
                'worker' => $site['app'],
                'project' => $site['project'],
                'data_source' => $site['data_source'],
                'url' => $site['url'],
                'locales' => $site['locales'] ?? [],
                'history' => array_map(
                    fn (array $build) => $this->presentBuild($build),
                    WorkersBuilds::history($worker),
                ),
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
     * Flatten Cloudflare's build shape into what the table renders.
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
            'branch' => $meta['branch'] ?? null,
            'commit_hash' => $meta['commit_hash'] ?? null,
            'commit_message' => $meta['commit_message'] ?? null,
            'author' => $meta['author'] ?? null,
            'trigger_source' => $meta['build_trigger_source'] ?? null,
        ];
    }

    /**
     * Whether prerendered content has changed since the last SUCCESSFUL build.
     *
     * A failed or in-flight build never counts as having shipped anything, so a
     * site whose build failed stays flagged until one succeeds.
     */
    protected function needsRebuild(?array $build, ?Carbon $contentChangedAt): bool
    {
        if (! $contentChangedAt) {
            return false;
        }

        $shippedAt = ($build && $build['status'] === 'stopped' && $build['outcome'] === 'success')
            ? $build['finished_at']
            : null;

        return $shippedAt === null || $contentChangedAt->gt(Carbon::parse($shippedAt));
    }
}
