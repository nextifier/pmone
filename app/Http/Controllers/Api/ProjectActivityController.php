<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ProjectActivityController extends Controller
{
    public function index(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        // Check if user is a project member or has admin access
        $user = $request->user();
        $isMember = $project->members()->where('user_id', $user->id)->exists();
        $isOwner = $project->created_by === $user->id;
        $isAdmin = $user->hasAnyRole(['master', 'admin']);

        if (! $isMember && ! $isOwner && ! $isAdmin) {
            return response()->json([
                'message' => 'Unauthorized. You must be a project member to view activity.',
            ], 403);
        }

        $perPage = min($request->input('per_page', 20), 100);
        $search = $request->input('search');

        $query = Activity::with(['causer:id,name', 'subject'])
            ->where('properties->project_id', $project->id)
            ->orderBy('created_at', 'desc');

        if ($search) {
            $likeOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('description', $likeOperator, "%{$search}%")
                    ->orWhere('event', $likeOperator, "%{$search}%")
                    ->orWhereHas('causer', function ($subQuery) use ($search, $likeOperator) {
                        $subQuery->where('name', $likeOperator, "%{$search}%");
                    });
            });
        }

        $activities = $query->paginate($perPage);

        $data = $activities->map(fn ($activity) => LogController::formatActivity($activity));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $activities->currentPage(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }
}
