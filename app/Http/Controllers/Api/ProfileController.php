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
            ->where('status', 'active')
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

        // Check visibility - allow public access for public projects
        if ($project->visibility !== 'public') {
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
     * Resolve and redirect short link
     */
    public function resolveShortLink(Request $request, string $slug): JsonResponse
    {
        $shortLink = ShortLink::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Track click
        TrackingHelper::trackClick($request, $shortLink);

        return response()->json([
            'data' => [
                'destination_url' => $shortLink->destination_url,
            ],
        ]);
    }
}
