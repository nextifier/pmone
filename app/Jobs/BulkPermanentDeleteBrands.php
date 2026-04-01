<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BulkPermanentDeleteBrands implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, string>  $brandSlugs
     */
    public function __construct(
        public string $jobId,
        public array $brandSlugs,
        public ?int $eventId = null,
    ) {}

    public function handle(): void
    {
        $query = Brand::query();
        if ($this->eventId) {
            $query->whereHas('events', fn ($q) => $q->where('events.id', $this->eventId));
        }
        $brands = $query->whereIn('slug', $this->brandSlugs)->get();

        $this->initProgress($brands->count(), 'Permanently deleting brands...');

        $deleted = 0;

        foreach ($brands as $index => $brand) {
            $brand->forceDelete();
            $deleted++;

            $this->updateProgress($index + 1);
        }

        $this->completeProgress(
            "{$deleted} brand(s) permanently deleted",
            ['deleted_count' => $deleted],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
