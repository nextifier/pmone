<?php

namespace App\Jobs;

use App\Models\Guest;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        foreach ($this->guestIds as $index => $id) {
            $guest = Guest::find($id);

            if ($guest) {
                if ($this->deletedBy) {
                    $guest->deleted_by = $this->deletedBy;
                    $guest->saveQuietly();
                }

                $guest->delete();
                $deleted++;
            }

            $this->updateProgress($index + 1);
        }

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
