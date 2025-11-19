<?php

namespace App\Console\Commands\Ghost;

use App\Models\Post;
use App\Services\Ghost\GhostImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrateFeaturedImagesToMediaLibrary extends Command
{
    protected $signature = 'ghost:migrate-featured-images
                            {--limit= : Number of posts to process (leave empty for all)}
                            {--dry-run : Run without saving changes}';

    protected $description = 'Migrate Ghost featured images to Spatie Media Library';

    protected string $ghostImagesPath;

    protected int $processed = 0;

    protected int $skipped = 0;

    protected int $errors = 0;

    public function handle(): int
    {
        $this->info('Starting Ghost featured images migration...');

        // Use base_path to get correct path in deployment environments
        $basePath = base_path();

        // Check if we're in a Forge deployment structure
        if (str_contains($basePath, '/releases/')) {
            // Use shared storage path
            $this->ghostImagesPath = str_replace('/releases/', '/shared/', $basePath).'/storage/app/post-migration/ghost/images';
        } else {
            // Normal path for local/non-Forge environments
            $this->ghostImagesPath = storage_path('app/post-migration/ghost/images');
        }

        if (! File::exists($this->ghostImagesPath)) {
            $this->error("Ghost images directory not found: {$this->ghostImagesPath}");
            $this->info("Tried path: {$this->ghostImagesPath}");

            return self::FAILURE;
        }

        $this->info("Using images path: {$this->ghostImagesPath}");

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        try {
            // Load Ghost data
            $importer = new GhostImporter;
            $ghostPosts = $importer->getData('posts');

            // Get PM One posts
            $query = Post::query()->where('source', 'ghost');

            if ($limit) {
                $query->limit($limit);
            }

            $posts = $query->get();

            if ($posts->isEmpty()) {
                $this->info('No Ghost posts found.');

                return self::SUCCESS;
            }

            $this->info("Found {$posts->count()} Ghost posts to process");

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No changes will be saved');
            }

            $this->newLine();

            $progressBar = $this->output->createProgressBar($posts->count());
            $progressBar->start();

            foreach ($posts as $post) {
                try {
                    // Find corresponding Ghost post
                    $ghostPost = collect($ghostPosts)->firstWhere('slug', $post->slug);

                    if (! $ghostPost) {
                        $this->skipped++;
                        $progressBar->advance();
                        continue;
                    }

                    $this->processPost($post, $ghostPost, $dryRun);
                    $progressBar->advance();
                } catch (\Exception $e) {
                    $this->errors++;
                    Log::error('Failed to process post featured image', [
                        'post_id' => $post->id,
                        'title' => $post->title,
                        'error' => $e->getMessage(),
                    ]);
                    $progressBar->advance();
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Show results
            $this->info('Migration completed!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Featured Images Migrated', $this->processed],
                    ['Skipped', $this->skipped],
                    ['Errors', $this->errors],
                ]
            );

            if ($dryRun) {
                $this->warn('This was a DRY RUN. No changes were saved.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to migrate featured images: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    protected function processPost(Post $post, array $ghostPost, bool $dryRun): void
    {
        // Check if already has featured image
        if ($post->hasMedia('featured_image')) {
            $this->skipped++;
            Log::info('Post already has featured image, skipping', [
                'post_id' => $post->id,
                'slug' => $post->slug,
            ]);

            return;
        }

        $featureImage = $ghostPost['feature_image'] ?? null;

        if (empty($featureImage)) {
            $this->skipped++;
            Log::info('Post has no feature_image in Ghost data, skipping', [
                'post_id' => $post->id,
                'slug' => $post->slug,
            ]);

            return;
        }

        // Extract image path - handle both __GHOST_URL__ placeholder and full URLs
        $imagePath = str_replace('__GHOST_URL__/content/images/', '', $featureImage);

        // If it's still a full URL, try to extract the path
        if (Str::startsWith($imagePath, ['http://', 'https://'])) {
            // Try to extract path from URL like https://blog.example.com/content/images/2023/image.jpg
            if (preg_match('/\/content\/images\/(.+)$/', $imagePath, $matches)) {
                $imagePath = $matches[1];
            } else {
                // Can't extract path, skip this
                $this->skipped++;
                Log::info('Skipping external featured image (cannot extract path)', [
                    'post_id' => $post->id,
                    'url' => $featureImage,
                ]);

                return;
            }
        }

        // Check if file exists
        $sourceFile = $this->ghostImagesPath.'/'.$imagePath;

        if (! File::exists($sourceFile)) {
            $this->skipped++;
            Log::warning('Featured image file not found', [
                'post_id' => $post->id,
                'path' => $sourceFile,
                'original_url' => $featureImage,
            ]);

            return;
        }

        if ($dryRun) {
            $this->processed++;

            return;
        }

        try {
            // Add to Media Library
            $post->addMedia($sourceFile)
                ->usingName(pathinfo($imagePath, PATHINFO_FILENAME))
                ->usingFileName(basename($imagePath))
                ->withCustomProperties([
                    'caption' => $ghostPost['feature_image_caption'] ?? null,
                    'alt' => $ghostPost['feature_image_alt'] ?? $post->title,
                ])
                ->toMediaCollection('featured_image');

            $this->processed++;

            Log::info('Featured image migrated', [
                'post_id' => $post->id,
                'image_path' => $imagePath,
            ]);
        } catch (\Exception $e) {
            $this->errors++;
            Log::error('Failed to add featured image to Media Library', [
                'post_id' => $post->id,
                'path' => $sourceFile,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
