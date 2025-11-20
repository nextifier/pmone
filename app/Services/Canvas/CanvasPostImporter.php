<?php

namespace App\Services\Canvas;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

class CanvasPostImporter
{
    protected int $created = 0;

    protected int $skipped = 0;

    protected array $errors = [];

    protected string $canvasImagesPath;

    protected User $balboaUser;

    public function __construct(
        protected CanvasImporter $importer,
        protected bool $dryRun = false,
        protected ?int $limit = null,
        protected ?\Symfony\Component\Console\Helper\ProgressBar $progressBar = null
    ) {
        $this->canvasImagesPath = storage_path('app/post-migration/canvas/images');

        // Get or create Balboa Estate user
        $this->balboaUser = User::firstOrCreate(
            ['email' => 'hello@balboaestate.id'],
            [
                'name' => 'Balboa Estate',
                'status' => 'active',
                'visibility' => 'public',
            ]
        );

        // Assign user role if newly created
        if ($this->balboaUser->wasRecentlyCreated && method_exists($this->balboaUser, 'assignRole')) {
            $this->balboaUser->assignRole('user');
        }
    }

    public function import(): array
    {
        $posts = $this->importer->getPosts();

        // Limit posts if specified
        if ($this->limit) {
            $posts = array_slice($posts, 0, $this->limit);
        }

        foreach ($posts as $canvasPost) {
            try {
                // Skip deleted posts
                if (! empty($canvasPost['deleted_at'])) {
                    // Advance progress bar even for skipped deleted posts
                    $this->progressBar?->advance();

                    continue;
                }

                $post = $this->importPost($canvasPost);

                if ($post && ! $this->dryRun) {
                    // Import post tags (both tags and topics merged into tags)
                    $this->importPostTags($post, $canvasPost['id']);
                }
            } catch (\Exception $e) {
                $this->errors[] = [
                    'title' => $canvasPost['title'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
                Log::error('Failed to import Canvas post', [
                    'title' => $canvasPost['title'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
            } finally {
                // Update progress bar after processing each post
                $this->progressBar?->advance();
            }
        }

        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'dry_run' => $this->dryRun,
        ];
    }

    protected function importPost(array $canvasPost): ?Post
    {
        // Check if post already exists by slug
        $existingPost = Post::query()->where('slug', $canvasPost['slug'])->first();

        if ($existingPost) {
            $this->skipped++;
            Log::info('Post already exists, skipping', ['slug' => $canvasPost['slug']]);

            return null;
        }

        if ($this->dryRun) {
            Log::info('DRY RUN: Would import post', [
                'title' => $canvasPost['title'],
                'slug' => $canvasPost['slug'],
            ]);
            $this->created++;

            return null;
        }

        // Process content
        $content = $this->processContent($canvasPost);

        // Parse meta JSON
        $meta = json_decode($canvasPost['meta'] ?? '{}', true);

        // Create post
        $post = Post::create([
            'title' => $canvasPost['title'],
            'slug' => $canvasPost['slug'],
            'excerpt' => $canvasPost['summary'] ?? null,
            'content' => $content,
            'content_format' => 'html',
            'meta_title' => $meta['title'] ?? $canvasPost['title'],
            'meta_description' => $meta['description'] ?? $canvasPost['summary'],
            'status' => $canvasPost['published_at'] ? 'published' : 'draft',
            'visibility' => 'public',
            'published_at' => $canvasPost['published_at'] ?? null,
            'featured' => false,
            'source' => 'canvas',
            'created_at' => $canvasPost['created_at'],
            'updated_at' => $canvasPost['updated_at'],
            'created_by' => $this->balboaUser->id,
        ]);

        // Attach Balboa Estate as author
        $post->authors()->attach($this->balboaUser->id, ['order' => 0]);

        // Process media after post creation
        $this->processFeaturedImage($post, $canvasPost);

        $this->created++;

        Log::info('Post imported successfully', [
            'canvas_id' => $canvasPost['id'],
            'pmone_id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
        ]);

        return $post;
    }

    protected function processContent(array $canvasPost): string
    {
        $content = $canvasPost['body'] ?? '';

        if (empty($content)) {
            return '';
        }

        // Process Canvas images (migrate to media library)
        $content = $this->processContentImages($content);

        return $content;
    }

    protected function processContentImages(string $content): string
    {
        // Match <img> tags and extract src
        preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $content, $matches);

        if (empty($matches[1])) {
            return $content;
        }

        foreach ($matches[0] as $index => $imgTag) {
            $src = $matches[1][$index];

            // Skip external images
            if (Str::startsWith($src, ['http://', 'https://'])) {
                continue;
            }

            // Extract filename from /storage/canvas/images/filename.jpg
            if (preg_match('/\/storage\/canvas\/images\/(.+)$/', $src, $pathMatches)) {
                $filename = $pathMatches[1];
                $sourceFile = $this->canvasImagesPath.'/'.$filename;

                if (File::exists($sourceFile)) {
                    // For now, keep the original path
                    // Content images will be migrated in a separate step if needed
                    // (Similar to Ghost migration approach)
                    continue;
                }
            }
        }

        return $content;
    }

    protected function processFeaturedImage(Post $post, array $canvasPost): void
    {
        $featuredImage = $canvasPost['featured_image'] ?? null;

        if (empty($featuredImage)) {
            return;
        }

        // Skip external images
        if (Str::startsWith($featuredImage, ['http://', 'https://'])) {
            Log::info('Skipping external featured image', ['url' => $featuredImage]);

            return;
        }

        // Extract filename from /storage/canvas/images/filename.jpg
        if (! preg_match('/\/storage\/canvas\/images\/(.+)$/', $featuredImage, $matches)) {
            Log::warning('Invalid featured image path', ['path' => $featuredImage]);

            return;
        }

        $filename = $matches[1];
        $sourceFile = $this->canvasImagesPath.'/'.$filename;

        if (! File::exists($sourceFile)) {
            Log::warning('Featured image not found', ['path' => $sourceFile]);

            return;
        }

        try {
            // Add to Media Library with caption support
            $post->addMedia($sourceFile)
                ->preservingOriginal()
                ->usingName(pathinfo($filename, PATHINFO_FILENAME))
                ->usingFileName(basename($filename))
                ->withCustomProperties([
                    'caption' => $canvasPost['featured_image_caption'] ?? null,
                    'alt' => $post->title,
                ])
                ->toMediaCollection('featured_image');

            Log::info('Featured image added to Media Library', [
                'post_id' => $post->id,
                'image_path' => $filename,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add featured image to Media Library', [
                'post_id' => $post->id,
                'path' => $sourceFile,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function importPostTags(Post $post, string $canvasPostId): void
    {
        // Get both tags and topics for this post
        $tags = $this->importer->getTagsForPost($canvasPostId);
        $topics = $this->importer->getTopicsForPost($canvasPostId);

        // Merge tags and topics
        $allTags = array_merge($tags, $topics);

        if (empty($allTags)) {
            return;
        }

        $tagsToAttach = [];

        foreach ($allTags as $canvasTag) {
            // Find or create tag in PM One using Spatie Tags
            $tag = Tag::findOrCreate($canvasTag['name'], 'post');

            $tagsToAttach[] = $tag;

            Log::info('Tag found/created', [
                'canvas_tag_id' => $canvasTag['id'],
                'canvas_tag_name' => $canvasTag['name'],
                'pmone_tag_id' => $tag->id,
            ]);
        }

        if (! empty($tagsToAttach)) {
            // Attach tags using Spatie Tags
            $post->syncTags($tagsToAttach);

            Log::info('Post tags attached', [
                'post_id' => $post->id,
                'tags_count' => count($tagsToAttach),
            ]);
        }
    }
}
