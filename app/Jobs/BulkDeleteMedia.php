<?php

namespace App\Jobs;

use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;

class BulkDeleteMedia implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $mediaIds
     * @param  array<int, string>  $responseCacheTags
     */
    public function __construct(
        public string $jobId,
        public array $mediaIds,
        public ?int $deletedBy = null,
        public array $responseCacheTags = [],
    ) {}

    public function handle(): void
    {
        $this->initProgress(count($this->mediaIds), 'Deleting photos...');

        $deletedIds = [];

        foreach ($this->mediaIds as $index => $id) {
            $media = Media::find($id);

            if ($media) {
                $media->delete();
                $deletedIds[] = $id;
            }

            $this->updateProgress($index + 1);
        }

        if (! empty($this->responseCacheTags)) {
            ResponseCache::clear($this->responseCacheTags);
        }

        $count = count($deletedIds);

        $this->completeProgress(
            "{$count} photo(s) deleted",
            ['deleted_count' => $count, 'deleted_ids' => $deletedIds],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
