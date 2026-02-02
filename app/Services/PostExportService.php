<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use ZipArchive;

class PostExportService
{
    /**
     * Maximum number of posts to include in images export
     */
    private const MAX_POSTS_WITH_IMAGES = 500;

    /**
     * Maximum total files to include in ZIP (originals + conversions)
     */
    private const MAX_TOTAL_FILES = 10000;

    /**
     * Maximum ZIP file size in MB
     */
    private const MAX_ZIP_SIZE_MB = 2000;

    public function __construct(
        private ?array $filters = null,
        private ?string $sort = null
    ) {}

    /**
     * Export posts with their images as a ZIP file
     *
     * @return array{path: string, filename: string}|array{error: string, code: int}
     */
    public function exportWithImages(): array
    {
        // Get ALL posts for the manifest (no limit)
        $allPosts = $this->buildQuery()->get();

        if ($allPosts->isEmpty()) {
            return [
                'error' => 'No posts found matching the filter criteria',
                'code' => 404,
            ];
        }

        // Limit posts for image export only
        $postsForImages = $allPosts->take(self::MAX_POSTS_WITH_IMAGES);

        // Create temporary directory for export
        $timestamp = now()->format('Y-m-d_His');
        $tempBaseDir = storage_path('app/temp');
        $exportDir = "{$tempBaseDir}/posts_export_{$timestamp}";

        try {
            // Ensure base temp directory exists
            File::ensureDirectoryExists($tempBaseDir);
            File::ensureDirectoryExists($exportDir);

            $totalFiles = 0;
            $totalSize = 0;
            $postsWithImages = [];

            foreach ($postsForImages as $post) {
                $mediaCollections = $this->collectPostMediaFolders($post);

                if (empty($mediaCollections)) {
                    continue;
                }

                $copiedCount = 0;

                foreach ($mediaCollections as $collectionInfo) {
                    // Check file count limit
                    if ($totalFiles >= self::MAX_TOTAL_FILES) {
                        break 2;
                    }

                    // Check total size limit
                    if (($totalSize + $collectionInfo['total_size']) / (1024 * 1024) > self::MAX_ZIP_SIZE_MB) {
                        break 2;
                    }

                    $copied = $this->copyMediaFolder($collectionInfo, $exportDir);
                    if ($copied) {
                        $copiedCount += $copied['file_count'];
                        $totalFiles += $copied['file_count'];
                        $totalSize += $copied['total_size'];
                    }
                }

                if ($copiedCount > 0) {
                    $postsWithImages[] = [
                        'post' => $post,
                        'files_copied' => $copiedCount,
                    ];
                }
            }

            // Generate manifest CSV with ALL post data (same format as PostsExport)
            $manifestPath = "{$exportDir}/posts_manifest.csv";
            $this->generateManifest($allPosts, $manifestPath);

            // Create ZIP file
            $zipFilename = "posts_images_{$timestamp}.zip";
            $zipPath = storage_path("app/temp/{$zipFilename}");

            $result = $this->createZipArchive($exportDir, $zipPath);

            if (! $result) {
                return [
                    'error' => 'Failed to create ZIP archive',
                    'code' => 500,
                ];
            }

            // Cleanup temporary directory
            File::deleteDirectory($exportDir);

            return [
                'path' => $zipPath,
                'filename' => $zipFilename,
                'stats' => [
                    'total_posts' => $allPosts->count(),
                    'posts_processed_for_images' => $postsForImages->count(),
                    'posts_with_images' => count($postsWithImages),
                    'total_files' => $totalFiles,
                    'total_size_mb' => round($totalSize / (1024 * 1024), 2),
                ],
            ];
        } catch (\Exception $e) {
            // Cleanup on error
            if (File::exists($exportDir)) {
                File::deleteDirectory($exportDir);
            }

            logger()->error('Post export with images failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => 'Export failed: '.$e->getMessage(),
                'code' => 500,
            ];
        }
    }

    /**
     * Build the query with filters and sorting
     */
    private function buildQuery(): Builder
    {
        $query = Post::query()
            ->with([
                'creator:id,name,email,username',
                'authors:id,name,email,username',
                'tags' => fn ($q) => $q->where('type', 'post'),
                'categories',
                'media',
            ])
            ->withCount(['visits', 'media']);

        if ($this->filters) {
            $this->applyFilters($query);
        }

        if ($this->sort) {
            $this->applySorting($query);
        } else {
            $query->orderBy('published_at', 'desc');
        }

        return $query;
    }

    /**
     * Apply filters to the query (same as PostController::applyFilters)
     */
    private function applyFilters(Builder $query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $statuses = array_map('trim', explode(',', $this->filters['status']));
            $statuses = array_filter($statuses);

            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } elseif (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            }
        }

        // Visibility filter
        if (isset($this->filters['visibility'])) {
            $visibilities = array_map('trim', explode(',', $this->filters['visibility']));
            $visibilities = array_filter($visibilities);

            if (count($visibilities) > 1) {
                $query->whereIn('visibility', $visibilities);
            } elseif (count($visibilities) === 1) {
                $query->where('visibility', $visibilities[0]);
            }
        }

        // Featured filter
        if (isset($this->filters['featured'])) {
            $query->where('featured', filter_var($this->filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        // Creator filter
        if (isset($this->filters['creator'])) {
            $creators = array_map('trim', explode(',', $this->filters['creator']));
            $creators = array_filter($creators);

            $hasNone = in_array('none', $creators);
            $creatorIds = array_filter($creators, fn ($c) => $c !== 'none');

            if ($hasNone && count($creatorIds) > 0) {
                $query->where(function ($q) use ($creatorIds) {
                    $q->whereNull('created_by')
                        ->orWhereIn('created_by', $creatorIds);
                });
            } elseif ($hasNone) {
                $query->whereNull('created_by');
            } elseif (count($creatorIds) > 1) {
                $query->whereIn('created_by', $creatorIds);
            } elseif (count($creatorIds) === 1) {
                $query->where('created_by', $creatorIds[0]);
            }
        }

        // Source filter
        if (isset($this->filters['source'])) {
            $sources = array_map('trim', explode(',', $this->filters['source']));
            $sources = array_filter($sources);

            if (count($sources) > 1) {
                $query->whereIn('source', $sources);
            } elseif (count($sources) === 1) {
                $query->where('source', $sources[0]);
            }
        }
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting(Builder $query): void
    {
        $direction = str_starts_with($this->sort, '-') ? 'desc' : 'asc';
        $field = ltrim($this->sort, '-');

        if (in_array($field, ['title', 'status', 'published_at', 'created_at', 'updated_at', 'visits_count', 'media_count'])) {
            $query->orderBy($field, $direction);
        } elseif ($field === 'creator') {
            $query->leftJoin('users', 'posts.created_by', '=', 'users.id')
                ->orderBy('users.name', $direction)
                ->select('posts.*');
        } else {
            $query->orderBy('published_at', 'desc');
        }
    }

    /**
     * Get the local storage base path for media
     */
    private function getLocalStoragePath(): string
    {
        return storage_path('app/public');
    }

    /**
     * Collect all media folders from a post (original + conversions)
     *
     * @return array<array{collection: string, post_id: int, source_dir: string, relative_path: string, total_size: int}>
     */
    private function collectPostMediaFolders(Post $post): array
    {
        $folders = [];
        $collections = ['featured_image', 'og_image', 'content_images'];

        foreach ($collections as $collection) {
            if (! $post->hasMedia($collection)) {
                continue;
            }

            $mediaItems = $post->getMedia($collection);

            foreach ($mediaItems as $media) {
                // Build the source directory path: posts/{collection}/{post_id}/
                $relativePath = "posts/{$collection}/{$post->id}";
                $sourceDir = $this->getLocalStoragePath()."/{$relativePath}";

                if (! File::exists($sourceDir)) {
                    continue;
                }

                // Calculate total size of directory (original + conversions)
                $totalSize = $this->getDirectorySize($sourceDir);

                $folders[] = [
                    'collection' => $collection,
                    'post_id' => $post->id,
                    'source_dir' => $sourceDir,
                    'relative_path' => $relativePath,
                    'total_size' => $totalSize,
                ];

                // Only one folder per collection per post (even for content_images, they share the same folder)
                break;
            }
        }

        return $folders;
    }

    /**
     * Get the total size of a directory including subdirectories
     */
    private function getDirectorySize(string $dir): int
    {
        $size = 0;

        if (! File::exists($dir)) {
            return $size;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Copy entire media folder to export directory (original + conversions)
     *
     * @return array{file_count: int, total_size: int}|null
     */
    private function copyMediaFolder(array $collectionInfo, string $exportDir): ?array
    {
        $sourceDir = $collectionInfo['source_dir'];
        $targetDir = "{$exportDir}/{$collectionInfo['relative_path']}";

        if (! File::exists($sourceDir)) {
            return null;
        }

        // Create target directory
        File::ensureDirectoryExists($targetDir);

        // Copy all files recursively (original + conversions folder)
        $fileCount = 0;
        $totalSize = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
            $targetPath = "{$targetDir}/{$relativePath}";

            if ($file->isDir()) {
                File::ensureDirectoryExists($targetPath);
            } else {
                // Ensure parent directory exists
                File::ensureDirectoryExists(dirname($targetPath));

                if (File::copy($file->getPathname(), $targetPath)) {
                    $fileCount++;
                    $totalSize += $file->getSize();
                }
            }
        }

        if ($fileCount === 0) {
            return null;
        }

        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
        ];
    }

    /**
     * Generate manifest CSV file with post data (same format as PostsExport)
     */
    private function generateManifest($posts, string $path): void
    {
        // Create CSV file directly
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \Exception('Failed to create manifest file');
        }

        // Write headers (same as PostsExport + Content column)
        $headers = [
            'ID',
            'ULID',
            'Title',
            'Slug',
            'Excerpt',
            'Content (Preview)',
            'Content',
            'Content Format',
            'Status',
            'Visibility',
            'Featured',
            'Published At',
            'Reading Time (min)',
            'Meta Title',
            'Meta Description',
            'Source',
            'Creator',
            'Authors',
            'Tags',
            'Categories',
            'Visits Count',
            'Media Count',
            'Featured Image URL',
            'OG Image URL',
            'Created At',
            'Updated At',
        ];
        fputcsv($handle, $headers);

        // Write post data
        foreach ($posts as $post) {
            // Truncate content to 500 characters for preview
            $contentPreview = $post->content
                ? \Illuminate\Support\Str::limit(strip_tags($post->content), 500)
                : '';

            $row = [
                $post->id,
                $post->ulid ?? '',
                $post->title,
                $post->slug,
                $post->excerpt ?? '',
                $contentPreview,
                $post->content ?? '',
                ucfirst($post->content_format ?? ''),
                ucfirst($post->status),
                ucfirst($post->visibility),
                $post->featured ? 'Yes' : 'No',
                $post->published_at?->format('Y-m-d H:i:s') ?? '',
                $post->reading_time ?? '',
                $post->meta_title ?? '',
                $post->meta_description ?? '',
                ucfirst($post->source ?? 'native'),
                $post->creator?->name ?? '',
                $post->authors->pluck('name')->join(', '),
                $post->tags->pluck('name')->join(', '),
                $post->categories->pluck('name')->join(', '),
                $post->visits_count ?? 0,
                $post->media_count ?? 0,
                $post->hasMedia('featured_image') ? $post->getFirstMediaUrl('featured_image', 'original') : '',
                $post->hasMedia('og_image') ? $post->getFirstMediaUrl('og_image', 'original') : '',
                $post->created_at?->format('Y-m-d H:i:s'),
                $post->updated_at?->format('Y-m-d H:i:s'),
            ];
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    /**
     * Create a ZIP archive from a directory
     */
    private function createZipArchive(string $sourceDir, string $zipPath): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    }
}
