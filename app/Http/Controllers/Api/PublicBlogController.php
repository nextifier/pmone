<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserMinimalResource;
use App\Models\Post;
use App\Models\User;
use App\Support\PaginationClamp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Spatie\Tags\Tag;

class PublicBlogController extends Controller
{
    /**
     * Columns the listing endpoints read. Everything PostResource's list branch
     * touches, plus the keys its eager loads hang off (`id` for media/tags/
     * authors, `created_by` for primaryAuthor) and the sortable columns.
     *
     * WHY THIS EXISTS. Without it Eloquent issues `select *` and hydrates
     * `content` — a JSON translations column holding the full article HTML in
     * every locale, ~11.5 KB per row per locale — for every row, only for
     * PostResource to discard it. At the default 50 per page that is megabytes
     * read and held in memory per uncached call, and the pmone-events sitemap
     * generator calls the same endpoint with per_page=1000.
     *
     * `content` is deliberately absent. The single-post endpoint builds its own
     * query and is untouched.
     *
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'id',
        'ulid',
        'title',
        'slug',
        'excerpt',
        'status',
        'visibility',
        'published_at',
        'featured',
        'reading_time',
        // Cheap to carry (one integer, already denormalised) and it is the field
        // that replaces the deprecated `visits_count` for consumers that want a
        // real total rather than a 90-day slice.
        'lifetime_views',
        'created_by',
        'settings',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Set the application locale from the request so translatable post fields
     * resolve to the requested language. The response cache hashes the full
     * URI including the query string, so each locale is cached separately.
     */
    protected function applyLocale(Request $request): string
    {
        $locale = (string) $request->input('locale', config('app.locale', 'en'));

        App::setLocale($locale);

        return $locale;
    }

    /**
     * Get list of published posts
     */
    public function posts(Request $request): JsonResponse
    {
        $this->applyLocale($request);

        // Responses are cached at the route level via the CacheResponse
        // middleware ('blog-posts' tag) and invalidated by Post's
        // ClearsResponseCache trait, so this method always fetches fresh data.
        return $this->fetchPosts($request);
    }

    /**
     * Fetch posts from database.
     */
    private function fetchPosts(Request $request): JsonResponse
    {
        $query = Post::query()
            ->select(self::LIST_COLUMNS)
            ->with([
                'primaryAuthor.media',
                'authors.media',
                'categories',
                'tags',
                'media',
            ])
            ->published()
            ->public()
            ->whereDoesntHave('tags', fn ($q) => $q->where('name->en', 'docs')->where('type', 'post'));

        $this->applyPostFilters($query, $request);
        $this->applyPostSorting($query, $request);

        // Higher ceiling than the other post listings: pmone-events' sitemap
        // generator fetches this endpoint with per_page=1000 to enumerate
        // every published post for /news/[slug] URLs, so a lower cap would
        // silently drop posts from the sitemap.
        $posts = $query->paginate(PaginationClamp::perPage($request, 15, 1000));

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
        $this->applyLocale($request);

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

        // Views are NOT counted here any more. This endpoint is what an event
        // website calls while server-rendering /news/{slug}, and once that HTML
        // is edge-cached the render stops happening — so a server-side counter
        // silently became a counter of cache misses. Measured: 23,300 views/day
        // fell to 4,359 within three days of the edge cache going live, while
        // banner and brand tracking (both client-side beacons) stayed flat.
        //
        // Counting now happens in the browser via POST /api/track/visit, the
        // same path useBannerTracking and useBrandTracking already use. That
        // also frees this endpoint to be response-cached — see routes/api.php.
        $post->loadCount('visits');

        return response()->json([
            'data' => new PostResource($post),
        ]);
    }

    /**
     * Get posts by category (using Spatie Tags with type 'category')
     */
    public function postsByCategory(Request $request, string $slug): JsonResponse
    {
        $this->applyLocale($request);

        $category = Tag::where('slug->en', $slug)->where('type', 'category')->firstOrFail();

        $query = Post::query()
            ->select(self::LIST_COLUMNS)
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
            ->published()
            ->public()
            ->withAnyTags([$category], 'category');

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate(PaginationClamp::perPage($request, 15));

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
        $this->applyLocale($request);

        $query = Post::query()
            ->select(self::LIST_COLUMNS)
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
            ->published()
            ->public()
            ->byTag($tag);

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate(PaginationClamp::perPage($request, 15));

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
        $this->applyLocale($request);

        $author = User::where('username', $username)->firstOrFail();

        $query = Post::query()
            ->select(self::LIST_COLUMNS)
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
            ->published()
            ->public()
            ->byAuthor($author->id);

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate(PaginationClamp::perPage($request, 15));

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
        $this->applyLocale($request);

        $query = Post::query()
            ->select(self::LIST_COLUMNS)
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
            ->published()
            ->public()
            ->featured();

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate(PaginationClamp::perPage($request, 10));

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
        $this->applyLocale($request);

        $searchTerm = $request->input('q');

        if (! $searchTerm) {
            return response()->json([
                'message' => 'Search term is required',
                'error' => 'Please provide a search term using the q parameter',
            ], 400);
        }

        $query = Post::query()
            ->select(self::LIST_COLUMNS)
            ->with(['primaryAuthor.media', 'authors.media', 'categories', 'tags', 'media'])
            ->published()
            ->public()
            ->search($searchTerm);

        $this->applyPostSorting($query, $request);

        $posts = $query->paginate(PaginationClamp::perPage($request, 15));

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
        $allowedFields = ['published_at', 'created_at', 'reading_time'];

        if ($field === 'title') {
            $query->orderByTitle($direction);
        } elseif (in_array($field, $allowedFields)) {
            $query->orderBy($field, $direction);
        } elseif ($field === 'view_count' || $field === 'visits_count') {
            // Ranked on the permanent lifetime total, which is what "most read"
            // means to anyone asking for it. Ordering on a count over `visits`
            // ranked articles by the last 90 days only, so a four-year-old piece
            // competed on a fraction of its readership and its position moved on
            // its own as old days aged out.
            //
            // The count is still selected so `visits_count` keeps its existing
            // value in the payload for consumers that read it; it is deprecated in
            // favour of `lifetime_views`.
            $query->withCount('visits')->orderBy('lifetime_views', $direction);
        } else {
            $query->orderBy('published_at', 'desc');
        }
    }
}
