<?php

namespace App\Http\Controllers\Api;

use App\Helpers\StaleWhileRevalidateCache;
use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserMinimalResource;
use App\Jobs\RefreshPublicBlogCacheJob;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Tags\Tag;

class PublicBlogController extends Controller
{
    /**
     * Stale TTL - after this time, data is considered stale and will trigger background refresh
     */
    private const STALE_TTL = 300; // 5 minutes

    /**
     * Max TTL - after this time, data expires completely and must be fetched synchronously
     */
    private const MAX_TTL = 3600; // 1 hour

    private const CACHE_TTL_CATEGORIES = 1800; // 30 minutes (categories change less often)

    /**
     * Get list of published posts
     */
    public function posts(Request $request): JsonResponse
    {
        $cacheKey = $this->generateCacheKey('posts', $request);
        $tags = ['public-blog', 'posts'];

        // Use stale-while-revalidate: return cached data immediately, refresh in background if stale
        return StaleWhileRevalidateCache::remember(
            $cacheKey,
            $tags,
            self::STALE_TTL,
            self::MAX_TTL,
            function () use ($request) {
                return $this->fetchPosts($request);
            },
            RefreshPublicBlogCacheJob::class,
            [
                'type' => 'posts',
                'per_page' => $request->input('per_page', 15),
                'sort' => $request->input('sort', '-published_at'),
            ]
        );
    }

    /**
     * Fetch posts from database (used by controller and background job)
     */
    private function fetchPosts(Request $request): JsonResponse
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
        $cacheKey = $this->generateCacheKey('categories', $request);

        return Cache::tags(['public-blog', 'categories'])->remember($cacheKey, self::CACHE_TTL_CATEGORIES, function () use ($request) {
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
        });
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
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
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
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
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
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
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
        $cacheKey = $this->generateCacheKey('featured', $request);
        $tags = ['public-blog', 'posts'];

        // Use stale-while-revalidate: return cached data immediately, refresh in background if stale
        return StaleWhileRevalidateCache::remember(
            $cacheKey,
            $tags,
            self::STALE_TTL,
            self::MAX_TTL,
            function () use ($request) {
                return $this->fetchFeaturedPosts($request);
            },
            RefreshPublicBlogCacheJob::class,
            [
                'type' => 'featured',
                'per_page' => $request->input('per_page', 10),
                'sort' => $request->input('sort', '-published_at'),
            ]
        );
    }

    /**
     * Fetch featured posts from database
     */
    private function fetchFeaturedPosts(Request $request): JsonResponse
    {
        $query = Post::query()
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
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
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
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
        // Only filter by authors who have published public posts to avoid user enumeration
        if ($authorUsername = $request->input('author')) {
            // Split by comma to support multiple authors (max 10 to prevent abuse)
            $usernames = array_slice(array_map('trim', explode(',', $authorUsername)), 0, 10);

            // Only get authors who have at least one published public post
            $authors = User::whereIn('username', $usernames)
                ->whereHas('posts', function ($q) {
                    $q->published()->public();
                })
                ->pluck('id')
                ->toArray();

            if (! empty($authors)) {
                $query->whereHas('authors', function ($q) use ($authors) {
                    $q->whereIn('users.id', $authors);
                });
            } else {
                // If no valid authors found, return no results
                $query->whereRaw('1 = 0');
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

        // Allowed direct column sorting
        $allowedFields = ['title', 'published_at', 'created_at', 'reading_time'];

        if (in_array($field, $allowedFields)) {
            $query->orderBy($field, $direction);
        } elseif ($field === 'view_count' || $field === 'visits_count') {
            // Sort by visits count using withCount (polymorphic visits relationship)
            $query->withCount('visits')->orderBy('visits_count', $direction);
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

    /**
     * Generate a unique cache key based on request parameters
     */
    private function generateCacheKey(string $prefix, Request $request): string
    {
        $params = $request->only([
            'page',
            'per_page',
            'sort',
            'search',
            'category',
            'tag',
            'author',
            'featured',
            'q',
            'root',
            'parent',
        ]);

        // Sort params for consistent cache keys
        ksort($params);

        return $prefix.':'.md5(json_encode($params));
    }
}
