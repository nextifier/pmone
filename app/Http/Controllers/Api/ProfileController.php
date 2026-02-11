<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get user profile by username
     */
    public function getUserProfile(Request $request, User $user): JsonResponse
    {
        $user->load([
            'links' => function ($query) {
                $query->active()->orderBy('order');
            },
            'roles',
        ]);

        // Note: Visit tracking is handled on the frontend via /api/track/visit endpoint
        // to prevent double tracking and allow better control over tracking behavior

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Get project profile by username
     */
    public function getProjectProfile(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)
            ->with([
                'links' => function ($query) {
                    $query->active()->orderBy('order');
                },
                'members' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.username');
                },
            ])
            ->first();

        if (! $project) {
            abort(404, 'Project not found. Please check the username and try again.');
        }

        // For non-active projects, require authentication and permission
        if ($project->status !== 'active') {
            if (! auth()->check() || ! auth()->user()->can('view', $project)) {
                abort(403, 'You do not have permission to view this project.');
            }
        }

        // Check visibility - allow public access for public projects with active status
        if ($project->visibility !== 'public' || $project->status !== 'active') {
            if (! auth()->check() || ! auth()->user()->can('view', $project)) {
                abort(403, 'You do not have permission to view this project.');
            }
        }

        // Note: Visit tracking is handled on the frontend via /api/track/visit endpoint
        // to prevent double tracking and allow better control over tracking behavior

        return response()->json([
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * Resolve a slug to either a user profile or short link
     */
    public function resolveSlug(string $slug): JsonResponse
    {
        // Check user first
        $user = User::where('username', $slug)->first();

        if ($user) {
            $user->load([
                'links' => function ($query) {
                    $query->active()->orderBy('order');
                },
                'roles',
            ]);

            return response()->json([
                'type' => 'user',
                'data' => new UserResource($user),
            ]);
        }

        // Check short link
        $shortLink = ShortLink::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($shortLink) {
            return response()->json([
                'type' => 'shortlink',
                'data' => [
                    'id' => $shortLink->id,
                    'slug' => $shortLink->slug,
                    'destination_url' => $shortLink->destination_url,
                    'og_title' => $shortLink->og_title,
                    'og_description' => $shortLink->og_description,
                    'og_image' => $shortLink->og_image,
                    'og_type' => $shortLink->og_type,
                ],
            ]);
        }

        abort(404, 'Page not found');
    }

    /**
     * Resolve and redirect short link
     */
    public function resolveShortLink(Request $request, string $slug): JsonResponse
    {
        $shortLink = ShortLink::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $shortLink) {
            abort(404, 'Page not found');
        }

        // Track click
        TrackingHelper::trackClick($request, $shortLink);

        return response()->json([
            'data' => [
                'slug' => $shortLink->slug,
                'destination_url' => $shortLink->destination_url,
                'og_title' => $shortLink->og_title,
                'og_description' => $shortLink->og_description,
                'og_image' => $shortLink->og_image,
                'og_type' => $shortLink->og_type,
            ],
        ]);
    }
}
