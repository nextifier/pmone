<?php

namespace App\Console\Commands\Canvas;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrateContentImagesToMediaLibrary extends Command
{
    protected $signature = 'canvas:migrate-content-images
                            {--limit= : Number of posts to process (leave empty for all)}
                            {--dry-run : Run without saving changes}';

    protected $description = 'Migrate Canvas content images to Spatie Media Library';

    protected string $canvasImagesPath;

    protected int $processed = 0;

    protected int $imagesAdded = 0;

    protected int $errors = 0;

    public function handle(): int
    {
        $this->info('Starting Canvas content images migration...');

        $this->canvasImagesPath = storage_path('app/post-migration/canvas/images');

        if (! File::exists($this->canvasImagesPath)) {
            $this->error("Canvas images directory not found: {$this->canvasImagesPath}");

            return self::FAILURE;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        // Get Canvas posts with images in content
        $query = Post::query()
            ->where('source', 'canvas')
            ->where('content', 'like', '%/storage/canvas/images/%');

        if ($limit) {
            $query->limit($limit);
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info('No posts with Canvas content images found.');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} posts with Canvas content images");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        $this->newLine();

        $progressBar = $this->output->createProgressBar($posts->count());
        $progressBar->start();

        foreach ($posts as $post) {
            try {
                $this->processPost($post, $dryRun);
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('Failed to process post content images', [
                    'post_id' => $post->id,
                    'title' => $post->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $this->info('Migration completed!');
        $this->info("Posts processed: {$this->processed}");
        $this->info("Images added to Media Library: {$this->imagesAdded}");

        if ($this->errors > 0) {
            $this->error("Errors: {$this->errors}");
        }

        if ($dryRun) {
            $this->warn('This was a DRY RUN. No changes were saved.');
        }

        return self::SUCCESS;
    }

    protected function processPost(Post $post, bool $dryRun): void
    {
        $content = $post->content;
        $originalContent = $content;

        // Match all Canvas image paths
        preg_match_all('/<img[^>]+src="\/storage\/canvas\/images\/([^"]+)"[^>]*>/i', $content, $matches);

        if (empty($matches[1])) {
            return;
        }

        foreach ($matches[0] as $index => $imgTag) {
            $filename = $matches[1][$index];
            $sourceFile = $this->canvasImagesPath.'/'.$filename;

            if (! File::exists($sourceFile)) {
                Log::warning('Canvas content image not found', [
                    'post_id' => $post->id,
                    'filename' => $filename,
                ]);
                continue;
            }

            if ($dryRun) {
                $this->imagesAdded++;
                continue;
            }

            // Add image to Media Library
            $media = $post->addMedia($sourceFile)
                ->usingName(pathinfo($filename, PATHINFO_FILENAME))
                ->usingFileName(basename($filename))
                ->toMediaCollection('content_images');

            // Get the new URL
            $newUrl = $media->getUrl();

            // Replace old path with new URL in content
            $content = str_replace(
                "/storage/canvas/images/{$filename}",
                $newUrl,
                $content
            );

            $this->imagesAdded++;

            Log::info('Canvas content image migrated', [
                'post_id' => $post->id,
                'old_path' => "/storage/canvas/images/{$filename}",
                'new_url' => $newUrl,
            ]);
        }

        // Save updated content if changed
        if (! $dryRun && $content !== $originalContent) {
            $post->content = $content;
            $post->saveQuietly();
            $this->processed++;
        }
    }
}
