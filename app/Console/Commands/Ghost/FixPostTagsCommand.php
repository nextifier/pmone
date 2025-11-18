<?php

namespace App\Console\Commands\Ghost;

use App\Models\Post;
use App\Services\Ghost\GhostImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Tags\Tag;

class FixPostTagsCommand extends Command
{
    protected $signature = 'ghost:fix-post-tags';

    protected $description = 'Fix tag attachments for Ghost imported posts';

    public function handle(): int
    {
        $this->info('Fixing post tags for Ghost imported posts...');

        try {
            $importer = new GhostImporter;
            $postsTags = $importer->getData('posts_tags');

            // Get all Ghost posts
            $posts = Post::query()
                ->where('source', 'ghost')
                ->get();

            if ($posts->isEmpty()) {
                $this->info('No posts found.');

                return self::SUCCESS;
            }

            $this->info("Found {$posts->count()} posts");
            $progressBar = $this->output->createProgressBar($posts->count());
            $progressBar->start();

            $fixed = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($posts as $post) {
                try {
                    // Find the Ghost post by slug
                    $ghostPosts = $importer->getData('posts');
                    $ghostPost = collect($ghostPosts)->firstWhere('slug', $post->slug);

                    if (! $ghostPost) {
                        $this->newLine();
                        $this->warn("Ghost post not found for slug: {$post->slug}");
                        $errors++;
                        $progressBar->advance();

                        continue;
                    }

                    // Find tags for this post
                    $postTagsRelations = array_filter($postsTags, fn ($pt) => $pt['post_id'] === $ghostPost['id']);

                    if (empty($postTagsRelations)) {
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    $tagsToAttach = [];

                    foreach ($postTagsRelations as $postTag) {
                        $pmoneTagId = $importer->getMapping('tags', $postTag['tag_id']);

                        if (! $pmoneTagId) {
                            continue;
                        }

                        $tagsToAttach[] = $pmoneTagId;
                    }

                    if (! empty($tagsToAttach)) {
                        // Get tag objects
                        $tags = Tag::query()->whereIn('id', $tagsToAttach)->get();

                        // Sync tags using Spatie Tags
                        $post->syncTags($tags);

                        $fixed++;
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Error fixing post {$post->slug}: {$e->getMessage()}");
                    Log::error('Failed to fix post tags', [
                        'post_id' => $post->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Fixed: {$fixed} posts");
            $this->info("Skipped (no tags): {$skipped} posts");
            if ($errors > 0) {
                $this->error("Errors: {$errors} posts");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to fix post tags: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
