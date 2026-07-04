<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\Og\OgScreenshotService;
use App\Traits\CapturesOgPage;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;
use Throwable;

/**
 * Capture every static website page as the project's OG images. Each run
 * screenshots ONE page then re-dispatches itself for the rest, so a single
 * run always stays under the pdf-batch supervisor's 120s timeout while the
 * whole batch reports through one shared progress entry. A page that fails
 * is recorded and skipped - one broken page never aborts the batch.
 *
 * @param  list<string>  $remainingKeys
 * @param  list<string>  $failedKeys
 */
class CaptureAllProjectOgImages implements ShouldQueue
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
        public array $remainingKeys,
        public int $totalKeys,
        public array $failedKeys = [],
    ) {
        $this->onQueue('pdf-batch');
    }

    public function handle(OgScreenshotService $screenshots): void
    {
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

        $pageKey = array_shift($this->remainingKeys);

        if ($pageKey === null) {
            $this->finish();

            return;
        }

        $processed = $this->totalKeys - count($this->remainingKeys);

        $this->updateProgress(
            max(0, $processed - 1),
            'Capturing '.Str::headline($pageKey)." ({$processed}/{$this->totalKeys})...",
        );

        try {
            $this->captureOgPage($screenshots, $project, $websiteUrl, $pageKey, "{$this->jobId}-{$pageKey}");
        } catch (Throwable $e) {
            $this->failedKeys[] = $pageKey;

            Log::warning('OG capture-all: page failed, continuing', [
                'project_id' => $this->projectId,
                'page_key' => $pageKey,
                'error' => $e->getMessage(),
            ]);
        }

        $this->updateProgress($processed, Str::headline($pageKey)." done ({$processed}/{$this->totalKeys})");

        if ($this->remainingKeys !== []) {
            static::dispatch($this->jobId, $this->projectId, $this->remainingKeys, $this->totalKeys, $this->failedKeys);

            return;
        }

        $this->finish();
    }

    protected function finish(): void
    {
        ResponseCache::clear(['website-settings']);

        $failed = count($this->failedKeys);
        $captured = $this->totalKeys - $failed;

        $message = $failed === 0
            ? "All {$captured} pages captured"
            : "{$captured} pages captured, {$failed} failed";

        $this->completeProgress($message, ['failed_keys' => $this->failedKeys]);

        Cache::forget("og-capture-all:{$this->projectId}");
    }

    public function failed(?Throwable $exception): void
    {
        $this->failProgress($exception?->getMessage() ?: 'Capture failed');

        Cache::forget("og-capture-all:{$this->projectId}");

        Log::warning('OG capture-all failed', [
            'project_id' => $this->projectId,
            'remaining_keys' => $this->remainingKeys,
            'error' => $exception?->getMessage(),
        ]);
    }
}
