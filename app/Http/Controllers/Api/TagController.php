<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Tags\Tag;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query()
            ->where('type', 'post')
            ->withCount(['posts' => function ($query) {
                $query->where('taggable_type', 'App\Models\Post');
            }]);

        // Sort by posts count (descending) or name (ascending)
        $sortField = $request->input('sort', '-posts_count');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if ($field === 'posts_count') {
            $query->orderBy('posts_count', $direction);
        } else {
            $query->orderBy('name', $direction);
        }

        // Client-only mode
        $clientOnly = $request->boolean('client_only', false);

        if ($clientOnly) {
            $tags = $query->get();

            return response()->json([
                'data' => TagResource::collection($tags),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $tags->count(),
                    'total' => $tags->count(),
                ],
            ]);
        }

        $tags = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => TagResource::collection($tags->items()),
            'meta' => [
                'current_page' => $tags->currentPage(),
                'last_page' => $tags->lastPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        // Query tag by slug (stored as JSON with 'en' locale)
        $tag = Tag::query()
            ->where('type', 'post')
            ->where(DB::raw("slug->>'en'"), $slug)
            ->withCount(['posts' => function ($query) {
                $query->where('taggable_type', 'App\Models\Post');
            }])
            ->firstOrFail();

        return response()->json([
            'data' => new TagResource($tag),
        ]);
    }
}
