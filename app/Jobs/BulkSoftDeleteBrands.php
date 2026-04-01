<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BulkSoftDeleteBrands implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $brandIds
     */
    public function __construct(
        public string $jobId,
        public array $brandIds,
        public ?int $deletedBy = null,
    ) {}

    public function handle(): void
    {
        $this->initProgress(count($this->brandIds), 'Deleting brands...');

        $deleted = 0;

        foreach ($this->brandIds as $index => $id) {
            $brand = Brand::find($id);

            if ($brand) {
                if ($this->deletedBy) {
                    $brand->deleted_by = $this->deletedBy;
                    $brand->saveQuietly();
                }

                $brand->delete();
                $deleted++;
            }

            $this->updateProgress($index + 1);
        }

        $this->completeProgress(
            "{$deleted} brand(s) deleted",
            ['deleted_count' => $deleted],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
