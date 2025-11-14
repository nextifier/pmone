<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish-scheduled
                            {--dry-run : Run in dry-run mode without actually publishing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish posts that are scheduled and past their publish date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for scheduled posts to publish...');

        // Find all scheduled posts that should be published
        $scheduledPosts = Post::where('status', 'scheduled')
            ->where('published_at', '<=', now())
            ->get();

        if ($scheduledPosts->isEmpty()) {
            $this->info('No scheduled posts to publish.');

            return self::SUCCESS;
        }

        $this->info("Found {$scheduledPosts->count()} post(s) to publish.");

        $publishedCount = 0;
        $failedCount = 0;

        foreach ($scheduledPosts as $post) {
            try {
                if ($this->option('dry-run')) {
                    $this->line("  [DRY RUN] Would publish: {$post->title} (ID: {$post->id})");
                } else {
                    // Update status to published
                    $post->update([
                        'status' => 'published',
                    ]);

                    $this->line("  ✓ Published: {$post->title} (ID: {$post->id})");
                    $publishedCount++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to publish: {$post->title} (ID: {$post->id})");
                $this->error("    Error: {$e->getMessage()}");
                $failedCount++;

                // Log error
                logger()->error('Failed to publish scheduled post', [
                    'post_id' => $post->id,
                    'post_title' => $post->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('Dry run completed. No posts were actually published.');
        } else {
            $this->info("✓ Successfully published: {$publishedCount} post(s)");

            if ($failedCount > 0) {
                $this->warn("✗ Failed to publish: {$failedCount} post(s)");
            }
        }

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
