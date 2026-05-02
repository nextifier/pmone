<?php

namespace App\Jobs;

use App\Models\Guest;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\ResponseCache\Facades\ResponseCache;

class BulkSoftDeleteGuests implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $guestIds
     */
    public function __construct(
        public string $jobId,
        public array $guestIds,
        public ?int $deletedBy = null,
    ) {}

    public function handle(): void
    {
        $this->initProgress(count($this->guestIds), 'Deleting guests...');

        $deleted = 0;
        $processed = 0;

        Guest::whereIn('id', $this->guestIds)
            ->chunkById(100, function ($guests) use (&$deleted, &$processed) {
                foreach ($guests as $guest) {
                    if ($this->deletedBy) {
                        $guest->deleted_by = $this->deletedBy;
                        $guest->saveQuietly();
                    }

                    $guest->delete();
                    $deleted++;
                    $processed++;
                    $this->updateProgress($processed);
                }
            });

        ResponseCache::clear(['guests']);

        $this->completeProgress(
            "{$deleted} guest(s) deleted",
            ['deleted_count' => $deleted],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
