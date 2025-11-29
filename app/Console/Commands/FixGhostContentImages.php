<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class FixGhostContentImages extends Command
{
    protected $signature = 'posts:fix-ghost-images {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Fix broken srcset attributes in Ghost-imported posts';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $posts = Post::where('source', 'ghost')
            ->where('content', 'like', '%srcset="%')
            ->get();

        $this->info("Found {$posts->count()} Ghost posts with srcset attributes");

        $fixed = 0;

        foreach ($posts as $post) {
            $originalContent = $post->content;
            $newContent = $this->fixSrcsetAttributes($originalContent);

            if ($originalContent !== $newContent) {
                $fixed++;

                if ($dryRun) {
                    $this->line("Would fix: {$post->title} (ID: {$post->id})");
                } else {
                    $post->content = $newContent;
                    $post->saveQuietly();
                    $this->line("Fixed: {$post->title} (ID: {$post->id})");
                }
            }
        }

        $action = $dryRun ? 'would be fixed' : 'fixed';
        $this->info("Done! {$fixed} posts {$action}.");

        return Command::SUCCESS;
    }

    private function fixSrcsetAttributes(string $content): string
    {
        // Pattern to match img tags with srcset containing relative paths (not starting with http)
        // This fixes srcset="2025/11/image.jpg 600w" to use the src URL instead
        return preg_replace_callback(
            '/<img\s+([^>]*?)src="([^"]+)"([^>]*?)srcset="([^"]+)"([^>]*?)>/i',
            function ($matches) {
                $beforeSrc = $matches[1];
                $src = $matches[2];
                $betweenSrcAndSrcset = $matches[3];
                $srcset = $matches[4];
                $afterSrcset = $matches[5];

                // Check if srcset contains relative paths (doesn't start with http)
                if (! preg_match('/^https?:\/\//', $srcset)) {
                    // Option 1: Remove srcset entirely (simpler, browser will use src)
                    // This is safer since the responsive images were never properly set up
                    return "<img {$beforeSrc}src=\"{$src}\"{$betweenSrcAndSrcset}{$afterSrcset}>";
                }

                // srcset is already absolute, keep as is
                return $matches[0];
            },
            $content
        );
    }
}
