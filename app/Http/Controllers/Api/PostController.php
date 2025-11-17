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
            ->with(['creator', 'tags']);

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

        // Track visit - record all views regardless of post status
        \App\Models\Visit::create([
            'visitable_type' => Post::class,
            'visitable_id' => $post->id,
            'visitor_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referer' => request()->header('referer'),
            'visited_at' => now(),
        ]);

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

            // Update post
            $post->update($data);

            // Update tags if provided
            if (isset($data['tags'])) {
                $post->syncTags($data['tags']);
            }

            // Handle featured image upload from temporary storage
            $this->handleTemporaryUpload($request, $post, 'tmp_featured_image', 'featured_image');

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
            ->with(['creator', 'deleter']);

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

        // Move file from temp storage to permanent storage
        $post->addMediaFromDisk($value, 'tmp')
            ->usingName(pathinfo($value, PATHINFO_FILENAME))
            ->toMediaCollection($collection);
    }
}
