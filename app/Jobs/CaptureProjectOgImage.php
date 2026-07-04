<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\Og\OgScreenshotService;
use App\Support\OgPages;
use App\Traits\CapturesOgPage;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Spatie\ResponseCache\Facades\ResponseCache;
use Throwable;

/**
 * Screenshot a live event-website page with Browsershot and store it as the
 * project's OG image for that page. Progress is reported through the shared
 * job-progress cache so the admin UI can poll it.
 */
class CaptureProjectOgImage implements ShouldQueue
{
    use CapturesOgPage, Queueable, TracksJobProgress;

    public int $tries = 1;

    /**
     * Browsershot times out at 110s; stay under the pdf-batch supervisor's 120s.
     */
    public int $timeout = 115;

    public function __construct(
        public string $jobId,
        public int $projectId,
        public string $pageKey,
    ) {
        $this->onQueue('pdf-batch');
    }

    public function handle(OgScreenshotService $screenshots): void
    {
        $this->initProgress(3, 'Preparing capture...');

        $project = Project::find($this->projectId);

        if (! $project) {
            $this->failProgress('Project not found');

            return;
        }

        $websiteUrl = $project->websiteUrl();

        if (! $websiteUrl) {
            $this->failProgress('Project has no "Website" link configured.');

            return;
        }

        $this->updateProgress(1, 'Capturing page...');

        $media = $this->captureOgPage($screenshots, $project, $websiteUrl, $this->pageKey, $this->jobId);

        $this->updateProgress(2, 'Saving image...');

        ResponseCache::clear(['website-settings']);

        $this->completeProgress('Capture complete', [
            'image' => [
                'url' => $media->getUrl(),
                'width' => OgPages::WIDTH,
                'height' => OgPages::HEIGHT,
            ],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->failProgress($exception?->getMessage() ?: 'Capture failed');

        File::delete(array_filter([
            storage_path("app/tmp/og-capture/{$this->jobId}.png"),
            storage_path("app/tmp/og-capture/{$this->jobId}.jpg"),
        ], 'is_file'));

        Log::warning('OG capture failed', [
            'project_id' => $this->projectId,
            'page_key' => $this->pageKey,
            'error' => $exception?->getMessage(),
        ]);
    }
}
