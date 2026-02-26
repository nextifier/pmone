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
        if (! $request->user()->hasRole(['master', 'admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only master and admin roles can access logs.',
            ], 403);
        }

        $perPage = min($request->input('per_page', 50), 100);
        $search = $request->input('search');
        $logName = $request->input('log_name');
        $event = $request->input('event');

        $query = Activity::with(['causer:id,name', 'subject'])
            ->orderBy('created_at', 'desc');

        if ($search) {
            $likeOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('description', $likeOperator, "%{$search}%")
                    ->orWhere('log_name', $likeOperator, "%{$search}%")
                    ->orWhere('event', $likeOperator, "%{$search}%");

                if (trim($search)) {
                    $q->orWhereHas('causer', function ($subQuery) use ($search, $likeOperator) {
                        $subQuery->where('name', $likeOperator, "%{$search}%");
                    });
                }
            });
        }

        if ($logName) {
            $query->where('log_name', $logName);
        }

        if ($event) {
            $query->where('event', $event);
        }

        $activities = $query->paginate($perPage);

        $data = $activities->map(fn ($activity) => self::formatActivity($activity));

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

    public function logNames(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole(['master', 'admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only master and admin roles can access logs.',
            ], 403);
        }

        $logNames = Activity::distinct()->pluck('log_name')->filter()->values();

        return response()->json([
            'data' => $logNames,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole(['master', 'admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only master and admin roles can access logs.',
            ], 403);
        }

        $events = Activity::distinct()->whereNotNull('event')->pluck('event')->filter()->values();

        return response()->json([
            'data' => $events,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master role can clear logs.',
            ], 403);
        }

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
    }

    /**
     * Format a single activity entry for API response.
     * Shared between LogController and ProjectActivityController.
     */
    public static function formatActivity(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'human_description' => self::generateHumanDescription($activity),
            'event' => $activity->event,
            'icon' => self::getEventIcon($activity->event),
            'color' => self::getEventColor($activity->event),
            'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'subject_name' => self::getSubjectName($activity),
            'causer_id' => $activity->causer_id,
            'causer_name' => $activity->causer?->name ?? 'System',
            'changes' => self::formatChanges($activity),
            'created_at' => $activity->created_at->toISOString(),
            'time_ago' => $activity->created_at->diffForHumans(),
        ];
    }

    private static function getSubjectName(Activity $activity): ?string
    {
        if (! $activity->subject_type) {
            return null;
        }

        $subjectType = class_basename($activity->subject_type);
        $subject = $activity->subject;

        if (! $subject) {
            // Try to get name from properties (for deleted subjects)
            $attrs = $activity->properties['attributes'] ?? $activity->properties['old'] ?? [];

            return $attrs['name'] ?? $attrs['title'] ?? null;
        }

        // User model: always use name (title = job title, not identifier)
        if ($subjectType === 'User') {
            return $subject->name;
        }

        // Other models: prefer title, then name, then slug
        return $subject->title ?? $subject->name ?? $subject->slug ?? null;
    }

    private static function generateHumanDescription(Activity $activity): string
    {
        $userName = $activity->causer?->name ?? 'System';
        $subjectType = $activity->subject_type ? class_basename($activity->subject_type) : null;
        $subjectName = self::getSubjectName($activity);
        $event = $activity->event;
        $description = $activity->description;
        $isSelf = $activity->causer_id && $activity->subject_id && $activity->causer_id === $activity->subject_id && $subjectType === 'User';

        switch ($event) {
            case 'created':
                $modelLabel = $subjectType ? self::humanizeModelName($subjectType) : 'record';
                if ($subjectName) {
                    return "{$userName} created {$modelLabel} \"{$subjectName}\"";
                }

                return "{$userName} created a new {$modelLabel}";

            case 'updated':
                $fields = self::getChangedFieldLabels($activity);

                if ($isSelf) {
                    return $fields
                        ? "{$userName} updated their {$fields}"
                        : "{$userName} updated their profile";
                }

                $modelLabel = $subjectType ? self::humanizeModelName($subjectType) : 'record';
                if ($fields && $subjectName) {
                    return "{$userName} updated {$fields} on {$modelLabel} \"{$subjectName}\"";
                }
                if ($subjectName) {
                    return "{$userName} updated {$modelLabel} \"{$subjectName}\"";
                }

                return "{$userName} updated a {$modelLabel}";

            case 'deleted':
                $modelLabel = $subjectType ? self::humanizeModelName($subjectType) : 'record';

                return $subjectName
                    ? "{$userName} deleted {$modelLabel} \"{$subjectName}\""
                    : "{$userName} deleted a {$modelLabel}";

            case 'restored':
                $modelLabel = $subjectType ? self::humanizeModelName($subjectType) : 'record';

                return $subjectName
                    ? "{$userName} restored {$modelLabel} \"{$subjectName}\""
                    : "{$userName} restored a {$modelLabel}";

            case 'member_added':
                $memberName = $activity->properties['member_name'] ?? 'a member';

                return "{$userName} added {$memberName} as a member";

            case 'member_removed':
                $memberName = $activity->properties['member_name'] ?? 'a member';

                return "{$userName} removed {$memberName} from members";

            case 'imported':
                return $description ?: "{$userName} imported data";

            default:
                if ($description === 'User logged in') {
                    return "{$userName} logged in";
                }

                if ($description === 'Activity logs cleared') {
                    return "{$userName} cleared all activity logs";
                }

                if ($description) {
                    return $description;
                }

                return "{$userName} performed an action";
        }
    }

    /**
     * Get human-readable field labels from changed attributes.
     */
    private static function getChangedFieldLabels(Activity $activity): ?string
    {
        $attributes = $activity->properties['attributes'] ?? [];
        $fields = array_keys($attributes);

        $fields = array_filter($fields, fn ($f) => ! in_array($f, ['updated_at', 'updated_by', 'created_at', 'created_by']));

        if (empty($fields)) {
            return null;
        }

        $labels = array_map(fn ($f) => self::humanizeFieldName($f), $fields);

        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' and '.$last;
    }

    private static function humanizeFieldName(string $field): string
    {
        $map = [
            'assignee_id' => 'assignee',
            'project_id' => 'project',
            'estimated_start_at' => 'start date',
            'estimated_completion_at' => 'due date',
            'completed_at' => 'completion date',
            'destination_url' => 'destination URL',
            'is_active' => 'active status',
            'booth_number' => 'booth number',
            'booth_size' => 'booth size',
            'booth_type' => 'booth type',
            'company_name' => 'company name',
            'birth_date' => 'birth date',
            'followed_up_at' => 'follow-up date',
            'followed_up_by' => 'follow-up person',
            'og_title' => 'OG title',
            'og_description' => 'OG description',
            'is_required' => 'required status',
            'published_at' => 'publish date',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'confirmed_at' => 'confirmation date',
            'submitted_at' => 'submission date',
            'website_url' => 'website URL',
            'rate_limit' => 'rate limit',
            'brand_event_id' => 'brand event',
            'event_id' => 'event',
        ];

        return $map[$field] ?? str_replace('_', ' ', $field);
    }

    private static function humanizeModelName(string $className): string
    {
        $map = [
            'ContactFormSubmission' => 'inquiry',
            'BrandEvent' => 'brand',
            'ShortLink' => 'short link',
            'PromotionPost' => 'promotion post',
            'EventProduct' => 'event product',
            'ProjectCustomField' => 'custom field',
        ];

        return $map[$className] ?? strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $className));
    }

    private static function getEventIcon(?string $event = null): string
    {
        return match ($event) {
            'created' => 'hugeicons:add-circle',
            'updated' => 'hugeicons:edit-02',
            'deleted' => 'hugeicons:delete-02',
            'restored' => 'hugeicons:refresh',
            'member_added' => 'hugeicons:user-add-01',
            'member_removed' => 'hugeicons:user-remove-01',
            'imported' => 'hugeicons:file-import',
            default => 'hugeicons:activity-01',
        };
    }

    private static function getEventColor(?string $event = null): string
    {
        return match ($event) {
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'restored' => 'amber',
            'member_added' => 'green',
            'member_removed' => 'red',
            'imported' => 'purple',
            default => 'zinc',
        };
    }

    /**
     * Format properties into readable changes array.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    private static function formatChanges(Activity $activity): array
    {
        $properties = $activity->properties;
        $changes = [];

        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        foreach ($attributes as $field => $newValue) {
            if (in_array($field, ['updated_at', 'updated_by', 'created_at', 'created_by'])) {
                continue;
            }

            $changes[] = [
                'field' => str_replace('_', ' ', $field),
                'old' => $old[$field] ?? null,
                'new' => $newValue,
            ];
        }

        // For custom events without attributes/old structure, show relevant properties
        if (empty($changes) && ! isset($properties['attributes'])) {
            $skip = ['project_id'];
            foreach ($properties->toArray() as $key => $value) {
                if (in_array($key, $skip)) {
                    continue;
                }
                $changes[] = [
                    'field' => str_replace('_', ' ', $key),
                    'old' => null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }
}
