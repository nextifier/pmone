<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrackingHelper;
use App\Http\Controllers\Controller;
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
    public function getUserProfile(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)
            ->with(['links' => function ($query) {
                $query->active()->orderBy('order');
            }])
            ->firstOrFail();

        // Track visit
        TrackingHelper::trackVisit($request, $user);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'ulid' => $user->ulid,
                'name' => $user->name,
                'username' => $user->username,
                'bio' => $user->bio,
                'profile_image' => $user->getFirstMediaUrl('profile_image'),
                'cover_image' => $user->getFirstMediaUrl('cover_image'),
                'links' => $user->links,
                'visibility' => $user->visibility,
            ],
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
            ->firstOrFail();

        // Check visibility
        if (! auth()->check() || ! auth()->user()->can('view', $project)) {
            abort(403, 'You do not have permission to view this project.');
        }

        // Track visit
        TrackingHelper::trackVisit($request, $project);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'ulid' => $project->ulid,
                'name' => $project->name,
                'username' => $project->username,
                'bio' => $project->bio,
                'profile_image' => $project->profile_image,
                'cover_image' => $project->cover_image,
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
