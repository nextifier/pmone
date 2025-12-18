<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class LogController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('admin.logs')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to access logs.',
            ], 403);
        }

        $perPage = min($request->input('per_page', 50), 100); // Max 100 entries per page
        $search = $request->input('search'); // Search in log messages
        $logName = $request->input('log_name'); // Filter by log name
        $event = $request->input('event'); // Filter by event type

        try {
            $query = Activity::with(['causer:id,name', 'subject'])
                ->orderBy('created_at', 'desc');

            // Apply search filter
            if ($search) {
                $likeOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('description', $likeOperator, "%{$search}%")
                        ->orWhere('log_name', $likeOperator, "%{$search}%")
                        ->orWhere('event', $likeOperator, "%{$search}%");

                    // Only search in causer if it exists to avoid N+1 issues
                    if (trim($search)) {
                        $q->orWhereHas('causer', function ($subQuery) use ($search, $likeOperator) {
                            $subQuery->where('name', $likeOperator, "%{$search}%");
                        });
                    }
                });
            }

            // Apply log name filter
            if ($logName) {
                $query->where('log_name', $logName);
            }

            // Apply event filter
            if ($event) {
                $query->where('event', $event);
            }

            $activities = $query->paginate($perPage);

            $data = $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'human_description' => $this->generateHumanDescription($activity),
                    'event' => $activity->event,
                    'subject_type' => $activity->subject_type,
                    'subject_id' => $activity->subject_id,
                    'subject_info' => $this->getSubjectInfo($activity),
                    'causer_type' => $activity->causer_type,
                    'causer_id' => $activity->causer_id,
                    'causer_name' => $activity->causer?->name,
                    'properties' => $activity->properties,
                    'batch_uuid' => $activity->batch_uuid,
                    'created_at' => $activity->created_at->toISOString(),
                    'formatted_time' => $activity->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $activities->currentPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                    'last_page' => $activities->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function logNames(): JsonResponse
    {
        $user = auth()->user();

        if (! $user->hasPermissionTo('admin.logs')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to access logs.',
            ], 403);
        }

        $logNames = Activity::distinct()->pluck('log_name')->filter()->values();

        return response()->json([
            'data' => $logNames,
        ]);
    }

    public function events(): JsonResponse
    {
        $user = auth()->user();

        if (! $user->hasPermissionTo('admin.logs')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to access logs.',
            ], 403);
        }

        $events = Activity::distinct()->whereNotNull('event')->pluck('event')->filter()->values();

        return response()->json([
            'data' => $events,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('admin.logs')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to clear logs.',
            ], 403);
        }

        try {
            $deletedCount = Activity::count();
            Activity::truncate();

            activity()
                ->causedBy($request->user())
                ->withProperties(['deleted_count' => $deletedCount])
                ->log('Activity logs cleared');

            return response()->json([
                'message' => 'Logs cleared successfully',
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function generateHumanDescription($activity): string
    {
        $userName = $activity->causer?->name ?? 'System';
        $subjectType = $activity->subject_type ? class_basename($activity->subject_type) : null;
        $event = $activity->event;
        $description = $activity->description;

        // Handle specific cases for better readability
        switch ($event) {
            case 'created':
                return "{$userName} created a new {$subjectType}";

            case 'updated':
                if ($activity->properties && isset($activity->properties['attributes'])) {
                    $attributes = array_keys($activity->properties['attributes']);
                    if (count($attributes) > 0) {
                        $fields = implode(', ', $attributes);

                        return "{$userName} updated {$subjectType} ({$fields})";
                    }
                }

                return "{$userName} updated {$subjectType}";

            case 'deleted':
                return "{$userName} deleted {$subjectType}";

            case 'restored':
                return "{$userName} restored {$subjectType}";

            default:
                // Handle custom descriptions
                if ($description === 'User logged in') {
                    return "{$userName} logged into the system";
                }

                if ($description === 'Activity logs cleared') {
                    return "{$userName} cleared all activity logs";
                }

                // For other cases, use the original description with user name
                if ($subjectType && $event) {
                    return "{$userName} performed '{$event}' on {$subjectType}";
                }

                return "{$userName}: {$description}";
        }
    }

    private function getSubjectInfo($activity): ?string
    {
        if (! $activity->subject_type || ! $activity->subject_id) {
            return null;
        }

        $subjectType = class_basename($activity->subject_type);

        try {
            // Try to get the actual subject model
            $subject = $activity->subject;

            if (! $subject) {
                return "{$subjectType} #{$activity->subject_id} (deleted)";
            }

            // Handle different model types
            switch ($subjectType) {
                case 'User':
                    return "{$subject->name} ({$subject->email})";

                case 'Role':
                    return "Role: {$subject->name}";

                case 'Permission':
                    return "Permission: {$subject->name}";

                default:
                    // For other models, try to get a name or title field
                    if (isset($subject->name)) {
                        return "{$subjectType}: {$subject->name}";
                    }

                    if (isset($subject->title)) {
                        return "{$subjectType}: {$subject->title}";
                    }

                    // Fallback to model with ID
                    return "{$subjectType} #{$activity->subject_id}";
            }
        } catch (\Exception) {
            // If we can't load the subject, show the basic info
            return "{$subjectType} #{$activity->subject_id}";
        }
    }
}
