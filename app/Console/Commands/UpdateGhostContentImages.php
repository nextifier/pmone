<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class UpdateGhostContentImages extends Command
{
    protected $signature = 'post:update-ghost-content-images {--dry-run : Run in dry-run mode without making changes}';

    protected $description = 'Update Ghost post content images to use responsive srcset';

    protected int $updated = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Updating Ghost content images with responsive srcset...');
        $this->newLine();

        // Get all Ghost posts with content_images
        $posts = Post::query()
            ->where('source', 'ghost')
            ->whereHas('media', function ($q) {
                $q->where('collection_name', 'content_images');
            })
            ->get();

        if ($posts->isEmpty()) {
            $this->warn('No Ghost posts with content images found.');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} Ghost posts to update");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($posts->count());
        $progressBar->start();

        foreach ($posts as $post) {
            if ($this->updatePostContentImages($post, $dryRun)) {
                $this->updated++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('Update completed!');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Posts Updated', $this->updated],
            ]
        );

        return self::SUCCESS;
    }

    protected function updatePostContentImages(Post $post, bool $dryRun): bool
    {
        $content = $post->content;
        $updated = false;

        // Get all content images for this post
        $contentImages = $post->getMedia('content_images');

        foreach ($contentImages as $media) {
            $mediaUrl = $media->getUrl();

            // Find img tag with this media URL
            $pattern = '/<img[^>]+src=["\']'.preg_quote($mediaUrl, '/').'["\'][^>]*>/i';

            if (preg_match($pattern, $content, $matches)) {
                $oldImgTag = $matches[0];

                // Build new responsive image HTML
                $newImgTag = $this->buildResponsiveImageHtml($media, $oldImgTag);

                // Replace old tag with new one
                $content = str_replace($oldImgTag, $newImgTag, $content);
                $updated = true;
            }
        }

        // Update post content
        if ($updated && ! $dryRun) {
            $post->update(['content' => $content]);
        }

        return $updated;
    }

    protected function buildResponsiveImageHtml($media, string $originalImgTag): string
    {
        // Extract caption from custom properties
        $caption = $media->getCustomProperty('caption');
        $alt = $media->getCustomProperty('alt') ?? $media->name;

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
