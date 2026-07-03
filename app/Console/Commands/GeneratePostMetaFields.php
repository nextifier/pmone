<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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

        $candidates = $this->candidates();

        if ($candidates->isEmpty()) {
            $this->info('All posts already have meta_title and meta_description filled.');

            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} posts needing meta backfill");

        if ($isDryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            $this->showPreview($candidates->take(10));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with updating these posts?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->updateMetaFields($candidates);

        return self::SUCCESS;
    }

    /**
     * Posts with at least one locale that has a title/excerpt but no matching
     * meta value. The columns hold locale-keyed json, so the check runs in PHP
     * via the same helper the model uses on save.
     *
     * @return Collection<int, Post>
     */
    protected function candidates()
    {
        return Post::query()
            ->withTrashed()
            ->get(['id', 'title', 'excerpt', 'meta_title', 'meta_description'])
            ->filter(function (Post $post) {
                foreach ($post->getTranslations('title') as $locale => $title) {
                    if (blank($post->getTranslation('meta_title', $locale, false))) {
                        return true;
                    }
                }

                foreach ($post->getTranslations('excerpt') as $locale => $excerpt) {
                    if (blank($post->getTranslation('meta_description', $locale, false))) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    protected function showPreview($posts): void
    {
        $this->newLine();
        $this->info('Preview of changes (showing first 10):');

        $tableData = $posts->map(function (Post $post) {
            return [
                'ID' => $post->id,
                'Title' => Str::limit($post->title, 30),
                'New Meta Title' => Str::limit($post->title, 30),
                'New Meta Desc' => Str::limit($post->excerpt ?? '', 30) ?: '(no excerpt)',
            ];
        })->toArray();

        $this->table(['ID', 'Title', 'New Meta Title', 'New Meta Desc'], $tableData);
    }

    protected function updateMetaFields($candidates): void
    {
        $updated = 0;

        $this->withProgressBar(
            Post::query()->withTrashed()->whereIn('id', $candidates->pluck('id'))->cursor(),
            function (Post $post) use (&$updated) {
                $post->fillMissingMetaTranslations();

                if ($post->isDirty(['meta_title', 'meta_description'])) {
                    $post->saveQuietly();
                    $updated++;
                }
            }
        );

        $this->newLine(2);
        $this->info("Updated {$updated} posts");
    }
}
