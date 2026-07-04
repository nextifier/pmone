<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePostOgImage;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GeneratePostOgImages extends Command
{
    protected $signature = 'posts:generate-og-images
                            {--all : Regenerate every post with a featured image (stale template versions included)}
                            {--delay=6 : Seconds between queued jobs (0 = full speed)}
                            {--limit= : Only dispatch the first N candidates (for a trial run)}
                            {--dry-run : Show what would be dispatched without dispatching}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Queue OG card generation for existing posts with a featured image';

    public function handle(): int
    {
        $delay = max(0, (int) $this->option('delay'));
        $postIds = $this->candidateIds();

        if ($postIds->isEmpty()) {
            $this->info('Every post with a featured image already has a generated OG card.');

            return self::SUCCESS;
        }

        $estimateMinutes = (int) ceil($postIds->count() * max($delay, 8) / 60);

        $this->info("Found {$postIds->count()} posts to generate OG cards for.");
        $this->line("Jobs are queued on pdf-batch with a {$delay}s stagger; estimated completion in ~{$estimateMinutes} minutes.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run mode - nothing was dispatched.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Dispatch these jobs?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        foreach ($postIds->values() as $i => $postId) {
            $dispatch = GeneratePostOgImage::dispatch($postId);

            if ($delay > 0 && $i > 0) {
                $dispatch->delay(now()->addSeconds($i * $delay));
            }
        }

        $this->info("Dispatched {$postIds->count()} jobs. Monitor progress in Horizon (pdf-batch queue).");
        $this->line('Safe to re-run at any time: already-generated posts are skipped (or no-op via the source hash with --all).');

        return self::SUCCESS;
    }

    /**
     * Posts with a featured image; without --all, only those still missing a
     * generated OG card so an interrupted run can simply be re-run.
     *
     * @return Collection<int, int>
     */
    protected function candidateIds(): Collection
    {
        $query = Post::query()
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'featured_image'))
            ->when(
                ! $this->option('all'),
                fn ($q) => $q->whereDoesntHave('media', fn ($m) => $m->where('collection_name', 'og_image_generated')),
            )
            ->orderBy('id');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        return $query->pluck('id');
    }
}
