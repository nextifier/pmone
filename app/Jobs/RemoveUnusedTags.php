<?php

namespace App\Jobs;

use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Spatie\Tags\Tag;

class RemoveUnusedTags implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  array<int>  $tagIds
     */
    public function __construct(
        public string $jobId,
        public array $tagIds,
    ) {}

    public function handle(): void
    {
        $this->initProgress(count($this->tagIds), 'Removing unused tags...');

        $removedCount = 0;

        foreach ($this->tagIds as $tagId) {
            $tag = Tag::find($tagId);

            if (! $tag) {
                $removedCount++;
                $this->updateProgress($removedCount);

                continue;
            }

            // Double-check tag is still unused (race condition safety)
            $isUsed = DB::table('taggables')
                ->where('tag_id', $tag->id)
                ->exists();

            if (! $isUsed) {
                $tag->delete();
            }

            $removedCount++;
            $this->updateProgress($removedCount);
        }

        $this->completeProgress(
            "{$removedCount} unused tag(s) removed",
            ['removed_count' => $removedCount],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
