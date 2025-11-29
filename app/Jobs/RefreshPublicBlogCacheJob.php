<?php

namespace App\Jobs;

use App\Helpers\StaleWhileRevalidateCache;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshPublicBlogCacheJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    public function __construct(
        public string $cacheKey,
        public array $tags,
        public int $maxTtl,
        public array $params = []
    ) {}

    public function handle(): void
    {
        $type = $this->params['type'] ?? 'posts';

        $callback = match ($type) {
            'posts' => $this->getPostsCallback(),
            'featured' => $this->getFeaturedCallback(),
            default => fn () => null,
        };

        if ($callback) {
            StaleWhileRevalidateCache::refresh(
                $this->cacheKey,
                $this->tags,
                $this->maxTtl,
                $callback
            );
        }
    }

    private function getPostsCallback(): \Closure
    {
        $params = $this->params;

        return function () use ($params) {
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

            // Apply sorting
            $sortField = $params['sort'] ?? '-published_at';
            $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
            $field = ltrim($sortField, '-');

            $allowedFields = ['title', 'published_at', 'created_at', 'reading_time'];

            if (in_array($field, $allowedFields)) {
                $query->orderBy($field, $direction);
            } elseif ($field === 'view_count' || $field === 'visits_count') {
                $query->withCount('visits')->orderBy('visits_count', $direction);
            } else {
                $query->orderBy('published_at', 'desc');
            }

            $posts = $query->paginate($params['per_page'] ?? 15);

            return response()->json([
                'data' => PostResource::collection($posts->items()),
                'meta' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ],
            ]);
        };
    }

    private function getFeaturedCallback(): \Closure
    {
        $params = $this->params;

        return function () use ($params) {
            $query = Post::query()
                ->with([
                    'primaryAuthor.media',
                    'authors.media',
                    'categories',
                    'tags',
                    'media',
                ])
                ->published()
                ->public()
                ->featured();

            // Apply sorting
            $sortField = $params['sort'] ?? '-published_at';
            $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
            $field = ltrim($sortField, '-');

            $allowedFields = ['title', 'published_at', 'created_at', 'reading_time'];

            if (in_array($field, $allowedFields)) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy('published_at', 'desc');
            }

            $posts = $query->paginate($params['per_page'] ?? 10);

            return response()->json([
                'data' => PostResource::collection($posts->items()),
                'meta' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ],
            ]);
        };
    }
}
