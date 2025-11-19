<?php

namespace App\Console\Commands\Ghost;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MigrateContentImagesToMediaLibrary extends Command
{
    protected $signature = 'ghost:migrate-content-images
                            {--limit= : Number of posts to process (leave empty for all)}
                            {--dry-run : Run without saving changes}';

    protected $description = 'Migrate Ghost content images to Spatie Media Library';

    protected string $ghostImagesPath;

    protected int $processed = 0;

    protected int $imagesAdded = 0;

    protected int $errors = 0;

    public function handle(): int
    {
        $this->info('Starting Ghost content images migration...');

        $this->ghostImagesPath = storage_path('app/post-migration/ghost/images');

        if (! File::exists($this->ghostImagesPath)) {
            $this->error("Ghost images directory not found: {$this->ghostImagesPath}");

            return self::FAILURE;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        // Get Ghost posts with images in content (images that don't start with http)
        $query = Post::query()
            ->where('source', 'ghost')
            ->where('content', 'like', '%<img%')
            ->where('content', 'not like', '%src="http%');

        if ($limit) {
            $query->limit($limit);
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info('No posts with Ghost content images found.');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} posts with Ghost content images");

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

        // Match all Ghost image paths that are relative (not starting with http)
        // Pattern: <img src="2025/10/filename.jpg" ... >
        preg_match_all('/<img[^>]+src="([^"http][^"]+)"[^>]*>/i', $content, $matches);

        if (empty($matches[1])) {
            return;
        }

        foreach ($matches[0] as $index => $imgTag) {
            $relativePath = $matches[1][$index];
            $sourceFile = $this->ghostImagesPath.'/'.$relativePath;

            if (! File::exists($sourceFile)) {
                Log::warning('Ghost content image not found', [
                    'post_id' => $post->id,
                    'relative_path' => $relativePath,
                    'source_file' => $sourceFile,
                ]);

                continue;
            }

            if ($dryRun) {
                $this->imagesAdded++;

                continue;
            }

            // Add image to Media Library
            $media = $post->addMedia($sourceFile)
                ->preservingOriginal()
                ->usingName(pathinfo($relativePath, PATHINFO_FILENAME))
                ->usingFileName(basename($relativePath))
                ->toMediaCollection('content_images');

            // Get the new URL
            $newUrl = $media->getUrl();

            // Replace old path with new URL in content
            $content = str_replace(
                "src=\"{$relativePath}\"",
                "src=\"{$newUrl}\"",
                $content
            );

            $this->imagesAdded++;

            Log::info('Ghost content image migrated', [
                'post_id' => $post->id,
                'old_path' => $relativePath,
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
