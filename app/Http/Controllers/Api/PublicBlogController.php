<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserMinimalResource;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Tags\Tag;

class PublicBlogController extends Controller
{
    /**
     * Get list of published posts
     */
    public function posts(Request $request): JsonResponse
    {
        $query = Post::query()
            ->with([
                'primaryAuthor.media',
                'authors.media',
                'categories',
                'tags',
                'media',
            ])
            ->published()
            ->public();

        $this->applyPostFilters($query, $request);
        $this->applyPostSorting($query, $request);

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

    /**
     * Get single post by slug
     */
    public function post(Request $request, string $slug): JsonResponse
    {
        $post = Post::query()
            ->with([
                'primaryAuthor.media',
                'authors.media',
                'categories',
                'tags',
                'media',
            ])
            ->where('slug', $slug)
            ->published()
            ->public()
            ->firstOrFail();

        // Track visit - no deduplication, always increment on refresh
        TrackingHelper::trackVisit($request, $post);

        // Load the updated visits count
        $post->loadCount('visits');

        return response()->json([
            'data' => new PostResource($post),
        ]);
    }

    /**
     * Get list of public categories
     */
    public function categories(Request $request): JsonResponse
    {
        $query = Category::query()
            ->with(['parent', 'children'])
            ->public();

        $this->applyCategoryFilters($query, $request);

        $categories = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => CategoryResource::collection($categories->items()),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Get single category by slug
     */
    public function category(Request $request, string $slug): JsonResponse
    {
        $category = Category::query()
            ->with(['parent', 'children'])
            ->where('slug', $slug)
            ->public()
            ->firstOrFail();

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Get posts by category (using Spatie Tags with type 'category')
     */
    public function postsByCategory(Request $request, string $slug): JsonResponse
    {
        $category = Tag::where('slug->en', $slug)->where('type', 'category')->firstOrFail();

        $query = Post::query()
            ->with(['primaryAuthor', 'authors', 'categories', 'tags'])
            ->published()
            ->public()
            ->withAnyTags([$category], 'category');

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'category' => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
            ],
        ]);
    }

    /**
     * Get posts by tag
     */
    public function postsByTag(Request $request, string $tag): JsonResponse
    {
        $query = Post::query()
            ->with(['primaryAuthor', 'authors', 'categories', 'tags'])
            ->published()
            ->public()
            ->byTag($tag);

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'tag' => $tag,
            ],
        ]);
    }

    /**
     * Get posts by author
     */
    public function postsByAuthor(Request $request, string $username): JsonResponse
    {
        $author = User::where('username', $username)->firstOrFail();

        $query = Post::query()
            ->with(['primaryAuthor', 'authors', 'categories', 'tags'])
            ->published()
            ->public()
            ->byAuthor($author->id);

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'author' => new UserMinimalResource($author),
            ],
        ]);
    }

    /**
     * Get featured posts
     */
    public function featured(Request $request): JsonResponse
    {
        $query = Post::query()
            ->with(['primaryAuthor', 'authors', 'categories', 'tags'])
            ->published()
            ->public()
            ->featured();

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate($request->input('per_page', 10));

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
     * Search posts
     */
    public function search(Request $request): JsonResponse
    {
        $searchTerm = $request->input('q');

        if (! $searchTerm) {
            return response()->json([
                'message' => 'Search term is required',
                'error' => 'Please provide a search term using the q parameter',
            ], 400);
        }

        $query = Post::query()
            ->with(['primaryAuthor', 'authors', 'categories', 'tags'])
            ->published()
            ->public()
            ->search($searchTerm);

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'search_term' => $searchTerm,
            ],
        ]);
    }

    /**
     * Apply filters to post query
     */
    private function applyPostFilters($query, Request $request): void
    {
        // Search filter
        if ($searchTerm = $request->input('search')) {
            $query->search($searchTerm);
        }

        // Category filter (using Spatie Tags with type 'category')
        if ($categorySlug = $request->input('category')) {
            $query->whereHas('tags', function ($q) use ($categorySlug) {
                $q->where('slug->en', $categorySlug)->where('type', 'category');
            });
        }

        // Tag filter
        if ($tag = $request->input('tag')) {
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where(function ($subQ) use ($tag) {
                    $subQ->where('name->en', $tag)
                        ->orWhere('slug->en', $tag);
                })->where('type', 'post');
            });
        }

        // Author filter (supports single author or comma-separated multiple authors)
        if ($authorUsername = $request->input('author')) {
            // Split by comma to support multiple authors
            $usernames = array_map('trim', explode(',', $authorUsername));

            $authors = User::whereIn('username', $usernames)->pluck('id')->toArray();

            if (! empty($authors)) {
                $query->whereHas('authors', function ($q) use ($authors) {
                    $q->whereIn('users.id', $authors);
                });
            }
        }

        // Featured filter
        if ($request->boolean('featured')) {
            $query->featured();
        }
    }

    /**
     * Apply sorting to post query
     */
    private function applyPostSorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-published_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if (in_array($field, ['title', 'published_at', 'view_count', 'created_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('published_at', 'desc');
        }
    }

    /**
     * Apply filters to category query
     */
    private function applyCategoryFilters($query, Request $request): void
    {
        // Root categories only
        if ($request->boolean('root')) {
            $query->whereNull('parent_id');
        }

        // Parent filter
        if ($parentSlug = $request->input('parent')) {
            $parent = Category::where('slug', $parentSlug)->first();
            if ($parent) {
                $query->where('parent_id', $parent->id);
            }
        }
    }
}
