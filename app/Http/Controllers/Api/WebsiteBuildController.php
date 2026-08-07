<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\TriggerWorkerBuild;
use App\Models\Project;
use App\Support\WorkersBuilds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Build status and manual rebuilds for the event websites.
 *
 * Every public page on those sites is prerendered, so content edited here only
 * reaches visitors on the next Cloudflare Workers build. This is where an
 * operator sees whether that has happened and starts it when it has not.
 */
class WebsiteBuildController extends Controller
{
    /**
     * Every configured site with its latest build.
     *
     * One batched Cloudflare call covers all sites, so this is cheap enough to
     * poll while a build is running.
     */
    public function index(): JsonResponse
    {
        $sites = WorkersBuilds::sites();
        $builds = WorkersBuilds::latestBuilds();

        $projectNames = Project::query()
            ->whereIn('username', array_column($sites, 'project'))
            ->pluck('name', 'username');

        $data = array_map(function (array $site) use ($builds, $projectNames): array {
            $build = $builds[$site['app']] ?? null;
            $meta = $build['build_trigger_metadata'] ?? [];

            return [
                'worker' => $site['app'],
                'project' => $site['project'],
                'project_name' => $projectNames[$site['project']] ?? $site['project'],
                'data_source' => $site['data_source'],
                'url' => $site['url'],
                'build' => $build ? [
                    'uuid' => $build['build_uuid'] ?? null,
                    // Cloudflare splits this across two fields: `status` is the
                    // lifecycle (queued/initializing/running/stopped) and only a
                    // stopped build has an outcome. Note the outcome for a
                    // failure is "fail", not "failed".
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
                ] : null,
            ];
        }, $sites);

        return response()->json([
            'data' => $data,
            'meta' => ['configured' => WorkersBuilds::isConfigured()],
        ]);
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
}
