<?php

namespace App\Jobs;

use App\Models\Partner;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BulkSoftDeletePartners implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $partnerIds
     */
    public function __construct(
        public string $jobId,
        public array $partnerIds,
        public ?int $deletedBy = null,
    ) {}

    public function handle(): void
    {
        $this->initProgress(count($this->partnerIds), 'Deleting partners...');

        $deleted = 0;

        foreach ($this->partnerIds as $index => $id) {
            $partner = Partner::find($id);

            if ($partner) {
                if ($this->deletedBy) {
                    $partner->deleted_by = $this->deletedBy;
                    $partner->saveQuietly();
                }

                $partner->delete();
                $deleted++;
            }

            $this->updateProgress($index + 1);
        }

        $this->completeProgress(
            "{$deleted} partner(s) deleted",
            ['deleted_count' => $deleted],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
