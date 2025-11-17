<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $query = Post::query()
            ->with(['creator', 'tags'])
            ->withCount('visits');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $posts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->search($searchTerm);
        }

        // Status filter
        if ($status = $request->input('filter_status')) {
            $query->where('status', $status);
        }

        // Visibility filter
        if ($visibility = $request->input('filter_visibility')) {
            $query->where('visibility', $visibility);
        }

        // Featured filter
        if ($request->has('filter_featured')) {
            $query->where('featured', $request->boolean('filter_featured'));
        }

        // Creator filter
        if ($creatorId = $request->input('filter_creator')) {
            $query->byCreator($creatorId);
        }

        // Tag filter
        if ($tag = $request->input('filter_tag')) {
            $query->byTag($tag);
        }

        // Source filter
        if ($source = $request->input('filter_source')) {
            $query->where('source', $source);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if (in_array($field, ['title', 'status', 'published_at', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    public function show(Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $post->load(['creator', 'updater', 'tags']);
        $post->loadCount('visits');

        // Track visit only if not loading for edit
        if (! request()->has('for') || request()->input('for') !== 'edit') {
            // Prevent duplicate visits within 5 minutes from same user/IP
            $recentVisit = \App\Models\Visit::where('visitable_type', Post::class)
                ->where('visitable_id', $post->id)
                ->where(function ($query) {
                    $query->where('visitor_id', auth()->id())
                        ->orWhere('ip_address', request()->ip());
                })
                ->where('visited_at', '>', now()->subMinutes(5))
                ->exists();

            if (! $recentVisit) {
                \App\Models\Visit::create([
                    'visitable_type' => Post::class,
                    'visitable_id' => $post->id,
                    'visitor_id' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'referer' => request()->header('referer'),
                    'visited_at' => now(),
                ]);

                // Increment the count after creating visit
                $post->visits_count++;
            }
        }

        return response()->json([
            'data' => new PostResource($post),
        ]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        try {
            $data = $request->validated();

            // Create post
            $post = Post::create($data);

            // Attach tags
            if (isset($data['tags'])) {
                $post->syncTags($data['tags']);
            }

            // Handle featured image upload from temporary storage
            $this->handleTemporaryUpload($request, $post, 'tmp_featured_image', 'featured_image');

            // Process content images (move from temp to permanent storage)
            $this->processContentImages($post);

            $post->load(['creator', 'tags', 'media']);

            return response()->json([
                'message' => 'Post created successfully',
                'data' => new PostResource($post),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Post creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Failed to create post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        try {
            $data = $request->validated();

            // Store old content before update for cleanup comparison
            $oldContent = $post->content;

            // Update post
            $post->update($data);

            // Update tags if provided
            if (isset($data['tags'])) {
                $post->syncTags($data['tags']);
            }

            // Handle featured image upload from temporary storage
            $this->handleTemporaryUpload($request, $post, 'tmp_featured_image', 'featured_image');

            // Process content images (move from temp to permanent storage)
            $this->processContentImages($post);

            // Cleanup removed content images
            $this->cleanupRemovedContentImages($post, $oldContent);

            $post->load(['creator', 'tags', 'media']);

            return response()->json([
                'message' => 'Post updated successfully',
                'data' => new PostResource($post),
            ]);
        } catch (\Exception $e) {
            logger()->error('Post update failed', [
                'error' => $e->getMessage(),
                'post_id' => $post->id,
            ]);

            return response()->json([
                'message' => 'Failed to update post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        try {
            $post->delete();

            return response()->json([
                'message' => 'Post deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('Post deletion failed', [
                'error' => $e->getMessage(),
                'post_id' => $post->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get trashed posts
     */
    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $query = Post::onlyTrashed()
            ->with(['creator', 'deleter'])
            ->withCount('visits');

        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->search($searchTerm);
        }

        $posts = $query->orderBy('deleted_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Restore a trashed post
     */
    public function restore(int $id): JsonResponse
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $post);

        $post->restore();

        return response()->json([
            'message' => 'Post restored successfully',
            'data' => new PostResource($post->fresh()),
        ]);
    }

    /**
     * Permanently delete a post
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $post);

        $post->forceDelete();

        return response()->json([
            'message' => 'Post permanently deleted',
        ]);
    }

    /**
     * Bulk delete posts
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize('delete', Post::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:posts,id',
        ]);

        $posts = Post::whereIn('id', $request->ids)->get();

        foreach ($posts as $post) {
            $this->authorize('delete', $post);
        }

        Post::whereIn('id', $request->ids)->delete();

        return response()->json([
            'message' => count($request->ids).' posts deleted successfully',
        ]);
    }

    /**
     * Bulk restore posts
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $this->authorize('restore', Post::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:posts,id',
        ]);

        Post::onlyTrashed()->whereIn('id', $request->ids)->restore();

        return response()->json([
            'message' => count($request->ids).' posts restored successfully',
        ]);
    }

    /**
     * Bulk force delete posts
     */
    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $this->authorize('forceDelete', Post::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:posts,id',
        ]);

        Post::onlyTrashed()->whereIn('id', $request->ids)->forceDelete();

        return response()->json([
            'message' => count($request->ids).' posts permanently deleted',
        ]);
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $this->authorize('update', Post::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:posts,id',
            'status' => 'required|in:draft,published,scheduled,archived',
        ]);

        $posts = Post::whereIn('id', $request->ids)->get();

        foreach ($posts as $post) {
            $this->authorize('update', $post);
        }

        Post::whereIn('id', $request->ids)->update(['status' => $request->status]);

        return response()->json([
            'message' => count($request->ids).' posts updated successfully',
        ]);
    }

    private function handleTemporaryUpload(Request $request, Post $post, string $fieldName, string $collection): void
    {
        // Check for delete flag first
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $post->clearMediaCollection($collection);

            return;
        }

        // If field is not present, do nothing (keep existing media)
        if (! $request->has($fieldName)) {
            return;
        }

        $value = $request->input($fieldName);

        // If value is null/empty, skip (already handled by delete flag above)
        if (! $value) {
            return;
        }

        // If value doesn't start with 'tmp-', it's an existing media URL, skip
        if (! \Illuminate\Support\Str::startsWith($value, 'tmp-')) {
            return;
        }

        // Clear existing media from collection first
        $post->clearMediaCollection($collection);

        // Get metadata to find the actual filename
        $metadataPath = "tmp/uploads/{$value}/metadata.json";
        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($metadataPath)) {
            throw new \Exception("File `{$value}` does not exist");
        }

        $metadata = json_decode(\Illuminate\Support\Facades\Storage::disk('local')->get($metadataPath), true);
        $filename = $metadata['original_name'];

        // Construct full path to the file in temporary storage
        $tempFilePath = "tmp/uploads/{$value}/{$filename}";

        // Move file from temp storage to permanent storage
        $post->addMediaFromDisk($tempFilePath, 'local')
            ->usingName(pathinfo($filename, PATHINFO_FILENAME))
            ->toMediaCollection($collection);

        // Clean up temporary storage
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }

    /**
     * Process content images - move temporary images to permanent storage
     */
    private function processContentImages(Post $post): void
    {
        if (! $post->content) {
            return;
        }

        $content = $post->content;
        $pattern = '/\/api\/tmp-media\/(tmp-media-[a-zA-Z0-9._-]+)/';

        // Find all temporary media URLs in content
        if (! preg_match_all($pattern, $content, $matches)) {
            return;
        }

        $tempFolders = array_unique($matches[1]);

        foreach ($tempFolders as $folder) {
            try {
                $metadataPath = "tmp/media/{$folder}/metadata.json";

                if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($metadataPath)) {
                    continue;
                }

                $metadata = json_decode(\Illuminate\Support\Facades\Storage::disk('local')->get($metadataPath), true);
                $filename = $metadata['original_name'];
                $tempFilePath = "tmp/media/{$folder}/{$filename}";

                if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($tempFilePath)) {
                    continue;
                }

                // Move file from temp storage to permanent storage
                $media = $post->addMediaFromDisk($tempFilePath, 'local')
                    ->usingName(pathinfo($filename, PATHINFO_FILENAME))
                    ->toMediaCollection('content_images');

                // Replace temporary URL with permanent URL in content
                $tempUrl = "/api/tmp-media/{$folder}";
                $permanentUrl = $media->getUrl();
                $content = str_replace($tempUrl, $permanentUrl, $content);

                // Clean up temporary storage
                \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("tmp/media/{$folder}");
            } catch (\Exception $e) {
                logger()->warning('Failed to process content image', [
                    'folder' => $folder,
                    'error' => $e->getMessage(),
                    'post_id' => $post->id,
                ]);
            }
        }

        // Update post content with new URLs
        if ($content !== $post->content) {
            $post->update(['content' => $content]);
        }
    }

    /**
     * Cleanup content images that were removed from post content
     */
    private function cleanupRemovedContentImages(Post $post, ?string $oldContent): void
    {
        if (! $oldContent || ! $post->content) {
            return;
        }

        // Extract all media URLs from old content
        $oldUrls = $this->extractMediaUrlsFromContent($oldContent);

        // Extract all media URLs from new content
        $newUrls = $this->extractMediaUrlsFromContent($post->content);

        // Find URLs that were removed
        $removedUrls = array_diff($oldUrls, $newUrls);

        if (empty($removedUrls)) {
            return;
        }

        // Delete media files that are no longer in content
        foreach ($removedUrls as $removedUrl) {
            try {
                // Find media by URL
                $media = $post->getMedia('content_images')
                    ->first(function ($item) use ($removedUrl) {
                        return str_contains($removedUrl, $item->file_name) ||
                               str_contains($removedUrl, basename($item->getPath()));
                    });

                if ($media) {
                    // This will delete the file, all conversions, and database record
                    $media->delete();

                    logger()->info('Deleted orphaned content image', [
                        'post_id' => $post->id,
                        'media_id' => $media->id,
                        'file_name' => $media->file_name,
                    ]);
                }
            } catch (\Exception $e) {
                logger()->warning('Failed to cleanup removed content image', [
                    'post_id' => $post->id,
                    'url' => $removedUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Extract all media URLs from HTML content
     */
    private function extractMediaUrlsFromContent(string $content): array
    {
        $urls = [];

        // Match img tags with src attribute
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $urls = array_merge($urls, $matches[1]);
        }

        // Also match direct URL patterns for media library
        if (preg_match_all('/\/storage\/media\/\d+\/[^"\')\s]+/i', $content, $matches)) {
            $urls = array_merge($urls, $matches[0]);
        }

        return array_unique($urls);
    }
}
