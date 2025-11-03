<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
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
     * Falls back to short link if user not found
     */
    public function getUserProfile(Request $request, string $username): JsonResponse
    {
        // Try to find user first
        $user = User::where('username', $username)
            ->with([
                'links' => function ($query) {
                    $query->active()->orderBy('order');
                },
                'roles',
            ])
            ->first();

        // If user found, return user profile
        if ($user) {
            // Note: Visit tracking is handled on the frontend via /api/track/visit endpoint
            // to prevent double tracking and allow better control over tracking behavior

            return response()->json([
                'data' => new UserResource($user),
            ]);
        }

        // If user not found, try short link as fallback
        $shortLink = ShortLink::where('slug', $username)
            ->where('is_active', true)
            ->first();

        if ($shortLink) {
            // Track click
            TrackingHelper::trackClick($request, $shortLink);

            return response()->json([
                'data' => [
                    'destination_url' => $shortLink->destination_url,
                ],
            ]);
        }

        // If both user and short link not found, return 404
        abort(404, 'User profile or short link not found');
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
            ->firstOrFail();

        // Check visibility - allow public access for public projects
        if ($project->visibility !== 'public') {
            if (! auth()->check() || ! auth()->user()->can('view', $project)) {
                abort(403, 'You do not have permission to view this project.');
            }
        }

        // Note: Visit tracking should be handled on the frontend via /api/track/visit endpoint
        // TODO: Implement frontend tracking for project profiles if needed
        // TrackingHelper::trackVisit($request, $project);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'ulid' => $project->ulid,
                'name' => $project->name,
                'username' => $project->username,
                'bio' => $project->bio,
                'profile_image' => $project->getFirstMediaUrl('profile_image'),
                'cover_image' => $project->getFirstMediaUrl('cover_image'),
                'email' => $project->email,
                'phone' => $project->phone,
                'links' => $project->links,
                'members' => $project->members,
                'settings' => $project->settings,
                'more_details' => $project->more_details,
                'visibility' => $project->visibility,
            ],
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
