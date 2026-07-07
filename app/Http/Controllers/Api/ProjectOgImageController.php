<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CaptureAllProjectOgImages;
use App\Jobs\CaptureProjectOgImage;
use App\Models\Project;
use App\Support\ImageOptimizer;
use App\Support\OgPages;
use App\Traits\HandlesTmpMediaUpload;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

class ProjectOgImageController extends Controller
{
    use AuthorizesRequests, HandlesTmpMediaUpload;

    public function show(Project $project): JsonResponse
    {
        return response()->json([
            'project_id' => $project->id,
            'website_url' => $project->websiteUrl(),
            'pages' => $this->pagesPayload($project),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $allowedKeys = implode(',', OgPages::KEYS);

        $request->validate([
            'pages' => ['nullable', "array:{$allowedKeys}"],
            'pages.*' => ['array:title,description'],
            'pages.*.title' => ['nullable', 'string', 'max:255'],
            'pages.*.description' => ['nullable', 'string', 'max:500'],
            'tmp_images' => ['nullable', "array:{$allowedKeys}"],
            'tmp_images.*' => ['string', 'starts_with:tmp-'],
            'delete_images' => ['nullable', "array:{$allowedKeys}"],
            'delete_images.*' => ['boolean'],
        ]);

        foreach ($request->input('delete_images', []) as $key => $delete) {
            if ($delete) {
                $project->clearMediaCollection(OgPages::collectionFor($key));
            }
        }

        foreach ($request->input('tmp_images', []) as $key => $tmpFolder) {
            $this->storeOgImage($project, $key, $tmpFolder);
        }

        $settings = $project->settings ?? [];

        foreach ($request->input('pages', []) as $key => $fields) {
            foreach (['title', 'description'] as $field) {
                if (array_key_exists($field, $fields)) {
                    data_set($settings, "website_settings.og_pages.{$key}.{$field}", $fields[$field]);
                }
            }
        }

        $project->settings = $settings;
        $project->save();

        ResponseCache::clear(["website-settings:{$project->username}"]);

        return response()->json([
            'project_id' => $project->id,
            'website_url' => $project->websiteUrl(),
            'pages' => $this->pagesPayload($project),
            'message' => 'OG images updated',
        ]);
    }

    /**
     * Queue a Browsershot screenshot of the live website page as its OG image.
     */
    public function capture(Request $request, Project $project, string $pageKey): JsonResponse
    {
        $this->authorize('update', $project);

        abort_unless(in_array($pageKey, OgPages::KEYS, true), 404);
        abort_if(! $project->websiteUrl(), 422, 'Project has no "Website" link configured.');

        $jobId = $this->seedJobProgress(3);

        CaptureProjectOgImage::dispatch($jobId, $project->id, $pageKey);

        return response()->json(['job_id' => $jobId]);
    }

    /**
     * Queue Browsershot screenshots of ALL static website pages as OG images.
     * One chained job per page, reporting through a single progress entry.
     * Only one batch may run per project at a time - concurrent batches would
     * race writing the same per-page collections.
     */
    public function captureAll(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        abort_if(! $project->websiteUrl(), 422, 'Project has no "Website" link configured.');

        $activeJobId = Cache::get("og-capture-all:{$project->id}");
        $activeStatus = $activeJobId ? Cache::get("job:{$activeJobId}")['status'] ?? null : null;

        abort_if(
            in_array($activeStatus, ['pending', 'processing'], true),
            409,
            'A capture batch is already running for this project.',
        );

        $jobId = $this->seedJobProgress(count(OgPages::KEYS));

        Cache::put("og-capture-all:{$project->id}", $jobId, now()->addMinutes(30));

        CaptureAllProjectOgImages::dispatch($jobId, $project->id, OgPages::KEYS, count(OgPages::KEYS));

        return response()->json(['job_id' => $jobId]);
    }

    /**
     * Pre-seed the job-progress cache so the frontend poller never 404s
     * before a worker picks the job up.
     */
    protected function seedJobProgress(int $total): string
    {
        $jobId = (string) Str::uuid();

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => $total,
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Waiting for worker...',
            'error_message' => null,
        ], now()->addMinutes(30));

        return $jobId;
    }

    /**
     * Move a tmp upload into the page's OG collection, cropping to 1200x630
     * on the tmp file first so the stored original is already final size.
     */
    protected function storeOgImage(Project $project, string $key, string $tmpFolder): void
    {
        $dimensions = null;

        $this->moveTempToMediaCollection(
            $project,
            $tmpFolder,
            OgPages::collectionFor($key),
            beforeAdd: function (string $path) use (&$dimensions): void {
                ImageOptimizer::cropToOg($path);

                if ($info = @getimagesize($path)) {
                    $dimensions = [$info[0], $info[1]];
                }
            },
        );

        $media = $project->getFirstMedia(OgPages::collectionFor($key));

        if ($media && $dimensions) {
            $media->setCustomProperty('width', $dimensions[0]);
            $media->setCustomProperty('height', $dimensions[1]);
            $media->save();
        }
    }

    /**
     * Stable 10-key payload: every canonical page key is always present.
     *
     * @return array<string, array{title: ?string, description: ?string, image: ?array{url: string, width: ?int, height: ?int}}>
     */
    protected function pagesPayload(Project $project): array
    {
        $ogPages = data_get($project->settings, 'website_settings.og_pages', []);

        $pages = [];

        foreach (OgPages::KEYS as $key) {
            $media = $project->getFirstMedia(OgPages::collectionFor($key));

            $pages[$key] = [
                'title' => data_get($ogPages, "{$key}.title"),
                'description' => data_get($ogPages, "{$key}.description"),
                'image' => $media ? [
                    'url' => $media->getUrl(),
                    'width' => $media->getCustomProperty('width'),
                    'height' => $media->getCustomProperty('height'),
                ] : null,
            ];
        }

        return $pages;
    }
}
