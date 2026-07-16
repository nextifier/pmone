<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserActivityLogRequest;
use App\Models\User;
use App\Support\AuthActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * What a single user created, updated and deleted, for the Activity tab.
 * Mirrors ProjectActivityController: same data/meta shape, same reuse of
 * LogController's eager-load and format helpers.
 */
class UserActivityLogController extends Controller
{
    public function index(UserActivityLogRequest $request, User $user): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $search = $request->input('search');

        $query = LogController::eagerLoadActivity(Activity::query())
            ->where('causer_type', (new User)->getMorphClass())
            ->where('causer_id', $user->id)
            ->orderByDesc('created_at');

        // Sign-ins belong to the Login History tab. Excluding exactly that set
        // keeps the two tabs from listing the same rows.
        AuthActivity::whereNotCausedLogin($query);

        if ($search) {
            $likeOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

            // No causer search: the causer is already fixed to one user.
            $query->where(function (Builder $q) use ($search, $likeOperator): void {
                $q->where('description', $likeOperator, "%{$search}%")
                    ->orWhere('event', $likeOperator, "%{$search}%")
                    ->orWhere('log_name', $likeOperator, "%{$search}%");
            });
        }

        $activities = $query->paginate($perPage);

        return response()->json([
            'data' => collect($activities->items())->map(fn (Activity $a): array => LogController::formatActivity($a)),
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
