<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutosavePostRequest;
use App\Http\Resources\PostAutosaveResource;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostAutosave;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostAutosaveController extends Controller
{
    use AuthorizesRequests;

    /**
     * Save or update autosave for a post
     */
    public function save(AutosavePostRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $request->user();

            // If post_id is provided, verify user has permission to edit it
            if (isset($data['post_id'])) {
                $post = Post::findOrFail($data['post_id']);
                $this->authorize('update', $post);
            }

            // Upsert autosave (one per user per post)
            $autosave = PostAutosave::updateOrCreate(
                [
                    'post_id' => $data['post_id'] ?? null,
                    'user_id' => $user->id,
                ],
                [
                    'title' => $data['title'] ?? '',
                    'excerpt' => $data['excerpt'] ?? null,
                    'content' => $data['content'] ?? '',
                    'content_format' => $data['content_format'] ?? 'html',
                    'meta_title' => $data['meta_title'] ?? null,
                    'meta_description' => $data['meta_description'] ?? null,
                    'status' => $data['status'] ?? 'draft',
                    'visibility' => $data['visibility'] ?? 'public',
                    'published_at' => $data['published_at'] ?? null,
                    'featured' => $data['featured'] ?? false,
                    'reading_time' => $data['reading_time'] ?? null,
                    'settings' => $data['settings'] ?? [],
                    'tmp_media' => $data['tmp_media'] ?? null,
                    'tags' => $data['tags'] ?? null,
                    'authors' => $data['authors'] ?? null,
                ]
            );

            return response()->json([
                'message' => 'Autosave successful',
                'data' => new PostAutosaveResource($autosave),
            ]);
        } catch (\Exception $e) {
            logger()->error('Autosave failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'post_id' => $data['post_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Autosave failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve autosave for a post
     */
    public function retrieve(Request $request): JsonResponse
    {
        $user = $request->user();
        $postId = $request->input('post_id');

        // If post_id is provided, verify user has permission to view it
        if ($postId) {
            $post = Post::findOrFail($postId);
            $this->authorize('view', $post);
        }

        $autosave = PostAutosave::where('user_id', $user->id)
            ->where('post_id', $postId)
            ->first();

        if (! $autosave) {
            return response()->json([
                'message' => 'No autosave found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => new PostAutosaveResource($autosave),
        ]);
    }

    /**
     * Discard autosave for a post
     */
    public function discard(Request $request): JsonResponse
    {
        $user = $request->user();
        $postId = $request->input('post_id');

        // If post_id is provided, verify user has permission to edit it
        if ($postId) {
            $post = Post::findOrFail($postId);
            $this->authorize('update', $post);
        }

        $autosave = PostAutosave::where('user_id', $user->id)
            ->where('post_id', $postId)
            ->first();

        if (! $autosave) {
            return response()->json([
                'message' => 'No autosave to discard',
            ], 404);
        }

        $autosave->delete();

        return response()->json([
            'message' => 'Autosave discarded successfully',
        ]);
    }

    /**
     * Preview changes - compare autosave with published version
     */
    public function preview(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();

        // Find the published post
        $post = Post::where('slug', $slug)->firstOrFail();
        $this->authorize('view', $post);

        // Find autosave for this post and user
        $autosave = PostAutosave::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if (! $autosave) {
            return response()->json([
                'message' => 'No autosave found for preview',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => [
                'published' => new PostResource($post),
                'autosave' => new PostAutosaveResource($autosave),
                'has_changes' => $this->hasChanges($post, $autosave),
            ],
        ]);
    }

    /**
     * Check if autosave has changes compared to published version
     */
    private function hasChanges(Post $post, PostAutosave $autosave): bool
    {
        $fieldsToCompare = [
            'title',
            'excerpt',
            'content',
            'content_format',
            'meta_title',
            'meta_description',
            'status',
            'visibility',
            'featured',
            'settings',
        ];

        foreach ($fieldsToCompare as $field) {
            // Convert both to same type for comparison
            $postValue = $post->{$field};
            $autosaveValue = $autosave->{$field};

            // Handle array/json fields
            if (is_array($postValue)) {
                $postValue = json_encode($postValue);
            }
            if (is_array($autosaveValue)) {
                $autosaveValue = json_encode($autosaveValue);
            }

            if ($postValue !== $autosaveValue) {
                return true;
            }
        }

        // Check published_at separately (datetime comparison)
        if ($post->published_at?->timestamp !== $autosave->published_at?->timestamp) {
            return true;
        }

        return false;
    }
}
