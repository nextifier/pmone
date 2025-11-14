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
            ->with(['primaryAuthor', 'authors', 'categories', 'tags']);

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

        // Author filter
        if ($authorId = $request->input('filter_author')) {
            $query->byAuthor($authorId);
        }

        // Category filter
        if ($categoryId = $request->input('filter_category')) {
            $query->byCategory($categoryId);
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

        if (in_array($field, ['title', 'status', 'published_at', 'view_count', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    public function show(Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $post->load(['primaryAuthor', 'authors', 'categories', 'tags', 'creator', 'updater']);

        // Increment view count for published posts
        if ($post->isPublished()) {
            $post->incrementViewCount();
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

            // Attach relationships
            if (isset($data['author_ids'])) {
                $post->authors()->sync($data['author_ids']);
            }

            if (isset($data['category_ids'])) {
                $post->categories()->sync($data['category_ids']);
            }

            if (isset($data['tags'])) {
                $post->syncTags($data['tags']);
            }

            $post->load(['primaryAuthor', 'authors', 'categories', 'tags']);

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

            // Update relationships if provided
            if (isset($data['author_ids'])) {
                $post->authors()->sync($data['author_ids']);
            }

            if (isset($data['category_ids'])) {
                $post->categories()->sync($data['category_ids']);
            }

            if (isset($data['tags'])) {
                $post->syncTags($data['tags']);
            }

            $post->load(['primaryAuthor', 'authors', 'categories', 'tags']);

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
}
