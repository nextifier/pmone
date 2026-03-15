<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait TracksJobProgress
{
    /**
     * Initialize progress tracking in cache.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function initProgress(int $total, string $message = 'Processing...', array $extra = []): void
    {
        Cache::put("job:{$this->jobId}", array_merge([
            'status' => 'processing',
            'total' => $total,
            'processed' => 0,
            'percentage' => 0,
            'message' => $message,
            'error_message' => null,
        ], $extra), now()->addMinutes(30));
    }

    /**
     * Update progress. Throttled to every 10 items to avoid excessive cache writes.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function updateProgress(int $processed, ?string $message = null, array $extra = []): void
    {
        // Update every 10 items to avoid excessive cache writes
        if ($processed % 10 !== 0) {
            return;
        }

        $data = Cache::get("job:{$this->jobId}");

        if (! $data) {
            return;
        }

        $total = $data['total'];
        $percentage = $total > 0 ? min(99, (int) round(($processed / $total) * 100)) : 0;

        Cache::put("job:{$this->jobId}", array_merge($data, $extra, [
            'processed' => $processed,
            'percentage' => $percentage,
            'message' => $message ?? $data['message'],
        ]), now()->addMinutes(30));
    }

    /**
     * Mark progress as completed.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function completeProgress(string $message = 'Completed', array $extra = []): void
    {
        $data = Cache::get("job:{$this->jobId}") ?? [];

        Cache::put("job:{$this->jobId}", array_merge($data, $extra, [
            'status' => 'completed',
            'processed' => $data['total'] ?? 0,
            'percentage' => 100,
            'message' => $message,
        ]), now()->addMinutes(30));
    }

    /**
     * Mark progress as failed.
     */
    protected function failProgress(string $errorMessage): void
    {
        $data = Cache::get("job:{$this->jobId}") ?? [];

        Cache::put("job:{$this->jobId}", array_merge($data, [
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]), now()->addMinutes(30));
    }
}
