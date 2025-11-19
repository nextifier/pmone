<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrateGhostImagesToMediaLibrary extends Command
{
    protected $signature = 'post:migrate-ghost-images {--dry-run : Run in dry-run mode without making changes}';

    protected $description = 'Migrate existing Ghost post images from public storage to Media Library';

    protected int $featuredMigrated = 0;

    protected int $contentImagesMigrated = 0;

    protected array $errors = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Starting Ghost images migration to Media Library...');
        $this->newLine();

        // Get all Ghost posts
        $posts = Post::query()
            ->where('source', 'ghost')
            ->get();

        if ($posts->isEmpty()) {
            $this->warn('No Ghost posts found.');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} Ghost posts to process");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($posts->count());
        $progressBar->start();

        foreach ($posts as $post) {
            try {
                $this->migratePost($post, $dryRun);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'post_id' => $post->id,
                    'post_title' => $post->title,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to migrate post images', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('Migration completed!');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Featured Images Migrated', $this->featuredMigrated],
                ['Content Images Migrated', $this->contentImagesMigrated],
                ['Errors', count($this->errors)],
            ]
        );

        if (! empty($this->errors)) {
            $this->newLine();
            $this->error('Errors encountered:');
            $this->table(
                ['Post ID', 'Title', 'Error'],
                array_map(fn ($error) => [
                    $error['post_id'],
                    Str::limit($error['post_title'], 50),
                    Str::limit($error['error'], 80),
                ], $this->errors)
            );
        }

        return self::SUCCESS;
    }

    protected function migratePost(Post $post, bool $dryRun): void
    {
        // Migrate featured image (from old database column to Media Library)
        if ($post->getRawOriginal('featured_image') && Str::contains($post->getRawOriginal('featured_image'), '/storage/posts/images/')) {
            $this->migrateFeaturedImage($post, $dryRun);
        }

        // Migrate content images (parse HTML and move to Media Library)
        if (! empty($post->content)) {
            $this->migrateContentImages($post, $dryRun);
        }
    }

    protected function migrateFeaturedImage(Post $post, bool $dryRun): void
    {
        $featuredImageUrl = $post->getRawOriginal('featured_image');

        // Extract path from URL
        // Example: "http://localhost:8000/storage/posts/images/2023/04/image.jpg"
        // Extract: "2023/04/image.jpg"
        $pattern = '/\/storage\/posts\/images\/(.+)$/';
        if (! preg_match($pattern, $featuredImageUrl, $matches)) {
            return;
        }

        $imagePath = $matches[1];
        $sourceFile = storage_path('app/public/posts/images/'.$imagePath);

        if (! File::exists($sourceFile)) {
            throw new \Exception("Featured image file not found: {$sourceFile}");
        }

        if ($dryRun) {
            $this->featuredMigrated++;

            return;
        }

        // Check if already migrated
        if ($post->hasMedia('featured_image')) {
            return;
        }

        // Add to Media Library
        $post->addMedia($sourceFile)
            ->preservingOriginal()
            ->usingName(pathinfo($imagePath, PATHINFO_FILENAME))
            ->usingFileName(basename($imagePath))
            ->toMediaCollection('featured_image');

        $this->featuredMigrated++;
    }

    protected function migrateContentImages(Post $post, bool $dryRun): void
    {
        $content = $post->content;

        // Pattern to match Ghost images with relative paths
        // Example: <img src="2023/04/image.jpg" ...>
        $pattern = '/<img[^>]+src="([^"https:\/\/][^"]+\.(?:jpg|jpeg|png|gif|webp|svg))"[^>]*>/i';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return;
        }

        $updatedContent = $content;
        $ghostImagesPath = storage_path('app/post-migration/ghost/images');

        foreach ($matches as $match) {
            $fullImgTag = $match[0];
            $imagePath = $match[1]; // e.g., "2023/04/image.jpg"

            // Build source file path from Ghost images directory
            $sourceFile = $ghostImagesPath.'/'.$imagePath;

            if (! File::exists($sourceFile)) {
                Log::warning('Content image file not found', [
                    'post_id' => $post->id,
                    'path' => $sourceFile,
                    'original_path' => $imagePath,
                ]);

                continue;
            }

            if ($dryRun) {
                $this->contentImagesMigrated++;

                continue;
            }

            // Extract caption from alt or title attribute
            $caption = null;
            if (preg_match('/alt="([^"]*)"/', $fullImgTag, $altMatch)) {
                $caption = html_entity_decode($altMatch[1]);
            } elseif (preg_match('/title="([^"]*)"/', $fullImgTag, $titleMatch)) {
                $caption = html_entity_decode($titleMatch[1]);
            }

            // Add to Media Library
            $mediaAdder = $post->addMedia($sourceFile)
                ->preservingOriginal()
                ->usingName(pathinfo($imagePath, PATHINFO_FILENAME))
                ->usingFileName(basename($imagePath));

            if ($caption) {
                $mediaAdder->withCustomProperties(['caption' => $caption]);
            }

            $media = $mediaAdder->toMediaCollection('content_images');

            // Build responsive image HTML with srcset
            $responsiveImg = $this->buildResponsiveImageHtml($media, $caption, $fullImgTag);

            // Replace entire img tag with responsive version
            $updatedContent = str_replace($fullImgTag, $responsiveImg, $updatedContent);

            $this->contentImagesMigrated++;
        }

        // Update post content with new URLs
        if ($updatedContent !== $content && ! $dryRun) {
            $post->update(['content' => $updatedContent]);
        }
    }

    /**
     * Build responsive image HTML with srcset for content images
     */
    protected function buildResponsiveImageHtml($media, ?string $caption, string $originalImgTag): string
    {
        $alt = $caption ?? $media->getCustomProperty('caption') ?? $media->name;

        // Extract width and height from original tag if available
        $width = null;
        $height = null;
        if (preg_match('/width="(\d+)"/', $originalImgTag, $match)) {
            $width = $match[1];
        }
        if (preg_match('/height="(\d+)"/', $originalImgTag, $match)) {
            $height = $match[1];
        }

        // Build srcset with all available conversions
        $srcset = [
            $media->getUrl('sm').' 600w',
            $media->getUrl('md').' 900w',
            $media->getUrl('lg').' 1200w',
            $media->getUrl('xl').' 1600w',
        ];

        $srcsetString = implode(', ', $srcset);

        // Default sizes attribute (responsive)
        $sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 1200px';

        $attributes = [
            'src="'.$media->getUrl('lg').'"',
            'srcset="'.$srcsetString.'"',
            'sizes="'.$sizes.'"',
            'alt="'.htmlspecialchars($alt, ENT_QUOTES, 'UTF-8').'"',
            'loading="lazy"',
            'class="w-full h-auto rounded-lg"',
        ];

        if ($width) {
            $attributes[] = 'width="'.$width.'"';
        }
        if ($height) {
            $attributes[] = 'height="'.$height.'"';
        }

        $html = '<img '.implode(' ', $attributes).'>';

        // Wrap with figure if caption exists
        if ($caption) {
            $html = sprintf(
                '<figure>%s<figcaption>%s</figcaption></figure>',
                $html,
                htmlspecialchars($caption, ENT_QUOTES, 'UTF-8')
            );
        }

        return $html;
    }
}
