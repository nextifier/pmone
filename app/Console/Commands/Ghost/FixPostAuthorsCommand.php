<?php

namespace App\Console\Commands\Ghost;

use App\Models\Post;
use App\Models\User;
use App\Services\Ghost\GhostImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixPostAuthorsCommand extends Command
{
    protected $signature = 'ghost:fix-post-authors';

    protected $description = 'Fix author attachments for Ghost imported posts';

    public function handle(): int
    {
        $this->info('Fixing post authors for Ghost imported posts...');

        try {
            $importer = new GhostImporter;
            $postsAuthors = $importer->getData('posts_authors');

            // Get all Ghost posts without authors
            $posts = Post::query()
                ->where('source', 'ghost')
                ->whereDoesntHave('authors')
                ->get();

            if ($posts->isEmpty()) {
                $this->info('No posts need fixing.');

                return self::SUCCESS;
            }

            $this->info("Found {$posts->count()} posts without authors");
            $progressBar = $this->output->createProgressBar($posts->count());
            $progressBar->start();

            $fixed = 0;
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

                    // Find authors for this post
                    $postAuthors = array_filter($postsAuthors, fn ($pa) => $pa['post_id'] === $ghostPost['id']);

                    if (empty($postAuthors)) {
                        // No authors, set created_by to first user
                        $firstUser = User::query()->first();
                        if ($firstUser) {
                            $post->created_by = $firstUser->id;
                            $post->saveQuietly();
                            $fixed++;
                        }
                    } else {
                        // Attach authors
                        foreach ($postAuthors as $postAuthor) {
                            $pmoneUserId = $importer->getMapping('users', $postAuthor['author_id']);

                            if (! $pmoneUserId) {
                                continue;
                            }

                            $post->authors()->attach($pmoneUserId, [
                                'role' => 'author',
                                'order' => $postAuthor['sort_order'] ?? 0,
                            ]);
                        }

                        // Set created_by to first author
                        $firstAuthor = $post->authors()->first();
                        if ($firstAuthor) {
                            $post->created_by = $firstAuthor->id;
                            $post->saveQuietly();
                            $fixed++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Error fixing post {$post->slug}: {$e->getMessage()}");
                    Log::error('Failed to fix post authors', [
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
            if ($errors > 0) {
                $this->error("Errors: {$errors} posts");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to fix post authors: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
