<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GeneratePostMetaFields extends Command
{
    protected $signature = 'posts:generate-meta
                            {--dry-run : Show what would be updated without making changes}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Auto-generate meta_title and meta_description for posts with empty values';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $postsNeedingMetaTitle = Post::query()
            ->where(function ($query) {
                $query->whereNull('meta_title')
                    ->orWhere('meta_title', '');
            })
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->count();

        $postsNeedingMetaDescription = Post::query()
            ->where(function ($query) {
                $query->whereNull('meta_description')
                    ->orWhere('meta_description', '');
            })
            ->whereNotNull('excerpt')
            ->where('excerpt', '!=', '')
            ->count();

        if ($postsNeedingMetaTitle === 0 && $postsNeedingMetaDescription === 0) {
            $this->info('All posts already have meta_title and meta_description filled.');

            return self::SUCCESS;
        }

        $this->info("Found {$postsNeedingMetaTitle} posts needing meta_title");
        $this->info("Found {$postsNeedingMetaDescription} posts needing meta_description");

        if ($isDryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            $this->showPreview();

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with updating these posts?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->updateMetaFields();

        return self::SUCCESS;
    }

    protected function showPreview(): void
    {
        $posts = Post::query()
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('meta_title')->orWhere('meta_title', '');
                })->orWhere(function ($q) {
                    $q->whereNull('meta_description')->orWhere('meta_description', '');
                });
            })
            ->limit(10)
            ->get(['id', 'title', 'excerpt', 'meta_title', 'meta_description']);

        if ($posts->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info('Preview of changes (showing first 10):');

        $tableData = $posts->map(function ($post) {
            return [
                'ID' => $post->id,
                'Title' => Str::limit($post->title, 30),
                'New Meta Title' => empty($post->meta_title) ? Str::limit($post->title, 30) : '(no change)',
                'New Meta Desc' => empty($post->meta_description) && ! empty($post->excerpt)
                    ? Str::limit($post->excerpt, 30)
                    : '(no change)',
            ];
        })->toArray();

        $this->table(['ID', 'Title', 'New Meta Title', 'New Meta Desc'], $tableData);
    }

    protected function updateMetaFields(): void
    {
        $metaTitleUpdated = 0;
        $metaDescriptionUpdated = 0;

        $this->withProgressBar(
            Post::query()
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('meta_title')->orWhere('meta_title', '');
                    })->orWhere(function ($q) {
                        $q->whereNull('meta_description')->orWhere('meta_description', '');
                    });
                })
                ->cursor(),
            function ($post) use (&$metaTitleUpdated, &$metaDescriptionUpdated) {
                $updates = [];

                if (empty($post->meta_title) && ! empty($post->title)) {
                    $updates['meta_title'] = $post->title;
                    $metaTitleUpdated++;
                }

                if (empty($post->meta_description) && ! empty($post->excerpt)) {
                    $updates['meta_description'] = Str::limit($post->excerpt, 160);
                    $metaDescriptionUpdated++;
                }

                if (! empty($updates)) {
                    $post->updateQuietly($updates);
                }
            }
        );

        $this->newLine(2);
        $this->info("Updated {$metaTitleUpdated} meta_title fields");
        $this->info("Updated {$metaDescriptionUpdated} meta_description fields");
    }
}
