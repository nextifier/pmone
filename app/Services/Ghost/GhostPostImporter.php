<?php

namespace App\Services\Ghost;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

class GhostPostImporter
{
    protected int $created = 0;

    protected int $skipped = 0;

    protected array $errors = [];

    protected string $ghostImagesPath;

    protected string $pmoneImagesPath;

    public function __construct(
        protected GhostImporter $importer,
        protected bool $dryRun = false,
        protected ?int $limit = null
    ) {
        $this->ghostImagesPath = storage_path('app/temp/images');
        $this->pmoneImagesPath = storage_path('app/public/posts/images');

        // Ensure destination directory exists
        if (! File::exists($this->pmoneImagesPath)) {
            File::makeDirectory($this->pmoneImagesPath, 0755, true);
        }
    }

    public function import(): array
    {
        $posts = $this->importer->getData('posts');
        $postsAuthors = $this->importer->getData('posts_authors');
        $postsTags = $this->importer->getData('posts_tags');

        // Limit posts if specified
        if ($this->limit) {
            $posts = array_slice($posts, 0, $this->limit);
        }

        foreach ($posts as $ghostPost) {
            try {
                // Skip non-post types (e.g., pages)
                if (($ghostPost['type'] ?? 'post') !== 'post') {
                    continue;
                }

                $post = $this->importPost($ghostPost);

                if ($post && ! $this->dryRun) {
                    // Import post authors
                    $this->importPostAuthors($post, $postsAuthors, $ghostPost['id']);

                    // Import post tags
                    $this->importPostTags($post, $postsTags, $ghostPost['id']);
                }
            } catch (\Exception $e) {
                $this->errors[] = [
                    'title' => $ghostPost['title'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
                Log::error('Failed to import Ghost post', [
                    'title' => $ghostPost['title'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'dry_run' => $this->dryRun,
        ];
    }

    protected function importPost(array $ghostPost): ?Post
    {
        // Check if post already exists by slug
        $existingPost = Post::query()->where('slug', $ghostPost['slug'])->first();

        if ($existingPost) {
            $this->skipped++;
            Log::info('Post already exists, skipping', ['slug' => $ghostPost['slug']]);

            return null;
        }

        if ($this->dryRun) {
            Log::info('DRY RUN: Would import post', [
                'title' => $ghostPost['title'],
                'slug' => $ghostPost['slug'],
                'status' => $ghostPost['status'],
            ]);
            $this->created++;

            return null;
        }

        // Process content
        $content = $this->processContent($ghostPost);

        // Process featured image
        $featuredImage = $this->processFeaturedImage($ghostPost);

        // Map status
        $status = $this->mapStatus($ghostPost['status']);

        // Map visibility
        $visibility = $this->mapVisibility($ghostPost['visibility']);

        // Create post
        $post = Post::create([
            'title' => $ghostPost['title'],
            'slug' => $ghostPost['slug'],
            'excerpt' => $ghostPost['custom_excerpt'], // null if not set
            'content' => $content,
            'content_format' => 'html',
            'featured_image' => $featuredImage,
            'meta_title' => $ghostPost['meta_title'] ?? $ghostPost['title'],
            'meta_description' => $ghostPost['meta_description'] ?? $ghostPost['custom_excerpt'],
            'og_image' => $ghostPost['og_image'] ?? null,
            'status' => $status,
            'visibility' => $visibility,
            'published_at' => $ghostPost['published_at'] ? $ghostPost['published_at'] : null,
            'featured' => (bool) ($ghostPost['featured'] ?? false),
            'source' => 'ghost',
            'created_at' => $ghostPost['created_at'],
            'updated_at' => $ghostPost['updated_at'],
        ]);

        $this->created++;

        Log::info('Post imported successfully', [
            'ghost_id' => $ghostPost['id'],
            'pmone_id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
        ]);

        return $post;
    }

    protected function processContent(array $ghostPost): string
    {
        $content = $ghostPost['html'] ?? '';

        if (empty($content)) {
            return '';
        }

        // Replace Ghost URL placeholder
        $content = str_replace('__GHOST_URL__/content/images/', '', $content);

        // Process Ghost images (copy from temp to public storage)
        $content = $this->processContentImages($content);

        // Clean up Ghost-specific CSS classes (optional)
        $content = $this->cleanupGhostClasses($content);

        return $content;
    }

    protected function processContentImages(string $content): string
    {
        // Match image tags with Ghost image paths
        $pattern = '/<img[^>]+src="([^"]+)"[^>]*>/i';

        $content = preg_replace_callback($pattern, function ($matches) {
            $originalSrc = $matches[1];
            $fullTag = $matches[0];

            // Skip Unsplash and other external images
            if (Str::startsWith($originalSrc, ['http://', 'https://'])) {
                return $fullTag;
            }

            // Extract the image path (e.g., "2023/04/image.jpg")
            $imagePath = $originalSrc;

            // Check if image exists in temp folder
            $sourceFile = $this->ghostImagesPath.'/'.$imagePath;

            if (! File::exists($sourceFile)) {
                Log::warning('Ghost image not found', ['path' => $sourceFile]);

                return $fullTag;
            }

            // Create destination path
            $destinationFile = $this->pmoneImagesPath.'/'.$imagePath;
            $destinationDir = dirname($destinationFile);

            // Create directory if not exists
            if (! File::exists($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true);
            }

            // Copy image
            try {
                File::copy($sourceFile, $destinationFile);
                Log::info('Image copied', [
                    'from' => $sourceFile,
                    'to' => $destinationFile,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to copy image', [
                    'path' => $sourceFile,
                    'error' => $e->getMessage(),
                ]);

                return $fullTag;
            }

            // Replace src with new path (use full backend URL for frontend access)
            $backendUrl = config('app.url');
            $newSrc = rtrim($backendUrl, '/').'/storage/posts/images/'.$imagePath;

            return str_replace($originalSrc, $newSrc, $fullTag);
        }, $content);

        return $content;
    }

    protected function processFeaturedImage(array $ghostPost): ?string
    {
        $featureImage = $ghostPost['feature_image'] ?? null;

        if (empty($featureImage)) {
            return null;
        }

        // Skip Unsplash and external images
        if (Str::startsWith($featureImage, ['http://', 'https://'])) {
            return $featureImage;
        }

        // Extract path
        $imagePath = str_replace('__GHOST_URL__/content/images/', '', $featureImage);

        // Check if exists in temp folder
        $sourceFile = $this->ghostImagesPath.'/'.$imagePath;

        if (! File::exists($sourceFile)) {
            Log::warning('Featured image not found', ['path' => $sourceFile]);

            return null;
        }

        // Copy to public storage
        $destinationFile = $this->pmoneImagesPath.'/'.$imagePath;
        $destinationDir = dirname($destinationFile);

        if (! File::exists($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true);
        }

        try {
            File::copy($sourceFile, $destinationFile);

            // Return full backend URL for frontend access
            $backendUrl = config('app.url');

            return rtrim($backendUrl, '/').'/storage/posts/images/'.$imagePath;
        } catch (\Exception $e) {
            Log::error('Failed to copy featured image', [
                'path' => $sourceFile,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function cleanupGhostClasses(string $content): string
    {
        // Remove Ghost-specific CSS classes
        $ghostClasses = [
            'kg-card',
            'kg-image-card',
            'kg-card-hascaption',
            'kg-image',
        ];

        foreach ($ghostClasses as $class) {
            $content = str_replace(' class="'.$class.'"', '', $content);
            $content = str_replace(' class="'.$class.' ', ' class="', $content);
        }

        // Remove empty class attributes
        $content = preg_replace('/ class=""/', '', $content);

        return $content;
    }

    protected function importPostAuthors(Post $post, array $postsAuthors, string $ghostPostId): void
    {
        // Find authors for this post
        $postAuthors = array_filter($postsAuthors, fn ($pa) => $pa['post_id'] === $ghostPostId);

        foreach ($postAuthors as $postAuthor) {
            $pmoneUserId = $this->importer->getMapping('users', $postAuthor['author_id']);

            if (! $pmoneUserId) {
                Log::warning('Author mapping not found', [
                    'ghost_author_id' => $postAuthor['author_id'],
                ]);

                continue;
            }

            // Attach author to post
            $post->authors()->attach($pmoneUserId, [
                'role' => 'author',
                'order' => $postAuthor['sort_order'] ?? 0,
            ]);

            Log::info('Post author attached', [
                'post_id' => $post->id,
                'user_id' => $pmoneUserId,
            ]);
        }

        // If no authors were attached, set created_by to first user
        if ($post->authors()->count() === 0) {
            $firstUser = User::query()->first();
            if ($firstUser) {
                $post->created_by = $firstUser->id;
                $post->saveQuietly();
            }
        } else {
            // Set created_by to first author
            $firstAuthor = $post->authors()->first();
            if ($firstAuthor) {
                $post->created_by = $firstAuthor->id;
                $post->saveQuietly();
            }
        }
    }

    protected function importPostTags(Post $post, array $postsTags, string $ghostPostId): void
    {
        // Find tags for this post
        $postTagsRelations = array_filter($postsTags, fn ($pt) => $pt['post_id'] === $ghostPostId);

        $tagsToAttach = [];

        foreach ($postTagsRelations as $postTag) {
            $pmoneTagId = $this->importer->getMapping('tags', $postTag['tag_id']);

            if (! $pmoneTagId) {
                Log::warning('Tag mapping not found', [
                    'ghost_tag_id' => $postTag['tag_id'],
                ]);

                continue;
            }

            $tagsToAttach[] = $pmoneTagId;
        }

        if (! empty($tagsToAttach)) {
            // Get tag objects
            $tags = Tag::query()->whereIn('id', $tagsToAttach)->get();

            // Attach tags using Spatie Tags
            $post->syncTags($tags);

            Log::info('Post tags attached', [
                'post_id' => $post->id,
                'tags_count' => count($tagsToAttach),
            ]);
        }
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'published' => 'published',
            'draft' => 'draft',
            'scheduled' => 'scheduled',
            default => 'draft',
        };
    }

    protected function mapVisibility(string $visibility): string
    {
        return match ($visibility) {
            'public' => 'public',
            'members' => 'members_only',
            'paid' => 'private',
            default => 'public',
        };
    }
}
