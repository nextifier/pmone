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
        $causerId = $request->input('causer_id');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = Activity::with(['causer', 'causer.media', 'subject'])
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
            $logNames = is_array($logName) ? $logName : explode(',', $logName);
            $logNames = array_filter($logNames);

            if (count($logNames) > 1) {
                $query->whereIn('log_name', $logNames);
            } elseif (count($logNames) === 1) {
                $query->where('log_name', $logNames[0]);
            }
        }

        if ($event) {
            $events = is_array($event) ? $event : explode(',', $event);
            $events = array_filter($events);

            if (count($events) > 1) {
                $query->whereIn('event', $events);
            } elseif (count($events) === 1) {
                $query->where('event', $events[0]);
            }
        }

        if ($causerId) {
            if ($causerId === 'system') {
                $query->whereNull('causer_id');
            } else {
                $query->where('causer_id', $causerId);
            }
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
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

    public function causers(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole(['master', 'admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only master and admin roles can access logs.',
            ], 403);
        }

        $causers = Activity::whereNotNull('causer_id')
            ->with('causer:id,name')
            ->select('causer_id')
            ->distinct()
            ->get()
            ->map(fn ($a) => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        // Prepend "System" option for activities without a causer
        $hasSystemActivities = Activity::whereNull('causer_id')->exists();
        if ($hasSystemActivities) {
            $causers->prepend(['id' => 'system', 'name' => 'System']);
        }

        return response()->json([
            'data' => $causers,
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
            'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'subject_name' => self::getSubjectName($activity),
            'subject_url' => self::getSubjectUrl($activity),
            'causer_id' => $activity->causer_id,
            'causer_name' => $activity->causer?->name ?? 'System',
            'causer' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name,
                'profile_image' => $activity->causer->relationLoaded('media')
                    ? $activity->causer->getMediaUrls('profile_image')
                    : null,
            ] : null,
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

        // BrandEvent: get name from related Brand
        if ($subjectType === 'BrandEvent') {
            return $subject->brand?->name;
        }

        // PromotionPost: use related brand name
        if ($subjectType === 'PromotionPost') {
            return $subject->brandEvent?->brand?->name;
        }

        // ContactFormSubmission: use subject field or extract name from form_data
        if ($subjectType === 'ContactFormSubmission') {
            if ($subject->subject) {
                return $subject->subject;
            }
            $formData = $subject->form_data ?? [];

            return $formData['name'] ?? $formData['email'] ?? null;
        }

        // Other models: prefer title, then name, then slug
        return $subject->title ?? $subject->name ?? $subject->slug ?? null;
    }

    private static function getSubjectUrl(Activity $activity): ?string
    {
        if (! $activity->subject_type || ! $activity->subject) {
            return null;
        }

        $subject = $activity->subject;
        $subjectType = class_basename($activity->subject_type);

        return match ($subjectType) {
            'Post' => $subject->slug ? "/posts/{$subject->slug}/edit" : null,
            'Brand' => $subject->slug ? "/brands/{$subject->slug}" : null,
            'User' => $subject->username ? "/users?search={$subject->username}" : null,
            'BrandEvent' => self::getBrandEventUrl($subject),
            'Event' => self::getEventUrl($subject),
            'Contact' => $subject->ulid ? "/contacts?open={$subject->ulid}" : null,
            'ContactFormSubmission' => $subject->ulid ? "/inbox?open={$subject->ulid}" : null,
            'ShortLink' => $subject->slug ? "/link-pages/{$subject->linkPage?->slug}" : null,
            'PromotionPost' => self::getPromotionPostUrl($subject),
            'LinkPage' => $subject->slug ? "/link-pages/{$subject->slug}" : null,
            'Task' => '/tasks',
            'Project' => $subject->username ? "/projects/{$subject->username}" : null,
            'EventProduct' => self::getEventProductUrl($subject),
            default => null,
        };
    }

    private static function getPromotionPostUrl(mixed $subject): ?string
    {
        $brandEvent = $subject->brandEvent;
        $brand = $brandEvent?->brand;
        $event = $brandEvent?->event;
        $project = $event?->project;
        if (! $brand?->slug || ! $event?->slug || ! $project?->username) {
            return null;
        }

        return "/projects/{$project->username}/events/{$event->slug}/brands/{$brand->slug}/marketing";
    }

    private static function getEventProductUrl(mixed $subject): ?string
    {
        $event = $subject->event;
        $project = $event?->project;
        if (! $event || ! $project) {
            return null;
        }

        return "/projects/{$project->username}/events/{$event->slug}/operational/products";
    }

    private static function getBrandEventUrl(mixed $subject): ?string
    {
        $event = $subject->event;
        $brand = $subject->brand;
        if (! $event || ! $brand) {
            return null;
        }
        $project = $event->project;
        if (! $project) {
            return null;
        }

        return "/projects/{$project->username}/events/{$event->slug}/brands/{$brand->slug}";
    }

    private static function getEventUrl(mixed $subject): ?string
    {
        $project = $subject->project;
        if (! $project) {
            return null;
        }

        return "/projects/{$project->username}/events/{$subject->slug}";
    }

    private static function generateHumanDescription(Activity $activity): string
    {
        $userName = $activity->causer?->name ?? 'System';
        $subjectType = $activity->subject_type ? class_basename($activity->subject_type) : null;
        $subjectName = self::getSubjectName($activity);
        // Truncate long subject names in description (full name available in subject_name field)
        if ($subjectName && mb_strlen($subjectName) > 80) {
            $subjectName = mb_substr($subjectName, 0, 77).'...';
        }
        $event = $activity->event;
        $description = $activity->description;
        $isSelf = $activity->causer_id && $activity->subject_id && $activity->causer_id === $activity->subject_id && $subjectType === 'User';

        switch ($event) {
            case 'created':
                if ($subjectType === 'BrandEvent' && $activity->subject) {
                    $brandName = $activity->subject->brand?->name;
                    $eventTitle = $activity->subject->event?->title;
                    if ($brandName && $eventTitle) {
                        return "{$userName} added brand \"{$brandName}\" to event \"{$eventTitle}\"";
                    }
                }
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

            case 'role_created':
                $roleName = $activity->properties['role_name'] ?? $subjectName ?? 'unknown';

                return "{$userName} created role \"{$roleName}\"";

            case 'role_updated':
                $roleName = $activity->properties['new_name'] ?? $activity->properties['role_name'] ?? $subjectName ?? 'unknown';

                return "{$userName} updated role \"{$roleName}\"";

            case 'role_deleted':
                $roleName = $activity->properties['role_name'] ?? $subjectName ?? 'unknown';

                return "{$userName} deleted role \"{$roleName}\"";

            case 'role_assigned':
                $roles = $activity->properties['new_roles'] ?? $activity->properties['roles'] ?? [];
                $roleList = is_array($roles) ? implode(', ', $roles) : $roles;
                $targetName = $subjectName ?? 'a user';

                return "{$userName} assigned role(s) {$roleList} to {$targetName}";

            case 'bulk_deleted':
                $count = $activity->properties['deleted_count'] ?? 0;
                $modelType = $activity->properties['model_type'] ?? 'record';

                return "{$userName} bulk deleted {$count} {$modelType}(s)";

            case 'bulk_restored':
                $count = $activity->properties['restored_count'] ?? 0;
                $modelType = $activity->properties['model_type'] ?? 'record';

                return "{$userName} bulk restored {$count} {$modelType}(s)";

            case 'bulk_force_deleted':
                $count = $activity->properties['deleted_count'] ?? 0;
                $modelType = $activity->properties['model_type'] ?? 'record';

                return "{$userName} permanently deleted {$count} {$modelType}(s)";

            case 'bulk_status_updated':
                $count = $activity->properties['updated_count'] ?? 0;
                $modelType = $activity->properties['model_type'] ?? 'record';
                $status = $activity->properties['new_status'] ?? 'unknown';

                return "{$userName} changed status to \"{$status}\" for {$count} {$modelType}(s)";

            case 'exported':
                $modelType = $activity->properties['model_type'] ?? 'data';

                return "{$userName} exported {$modelType}";

            case 'api_key_regenerated':
                $consumerName = $activity->properties['consumer_name'] ?? $subjectName ?? 'unknown';

                return "{$userName} regenerated API key for \"{$consumerName}\"";

            case 'password_changed':
                $viaReset = $activity->properties['via_reset'] ?? false;

                if ($isSelf) {
                    return $viaReset
                        ? "{$userName} reset their password"
                        : "{$userName} changed their password";
                }

                return $viaReset
                    ? "{$userName} reset password for {$subjectName}"
                    : "{$userName} changed password for {$subjectName}";

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

            $oldValue = $old[$field] ?? null;

            // Skip changes where old and new are effectively the same
            if (self::isEffectivelySame($oldValue, $newValue)) {
                continue;
            }

            $changes[] = [
                'field' => str_replace('_', ' ', $field),
                'old' => $oldValue,
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
                // Skip null/empty values for custom events too
                if ($value === null || $value === '') {
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

    /**
     * Check if two values are effectively the same (both empty/null or equal).
     */
    private static function isEffectivelySame(mixed $old, mixed $new): bool
    {
        // Both null or empty string
        if (($old === null || $old === '') && ($new === null || $new === '')) {
            return true;
        }

        // Loose comparison for type juggling (e.g. "1" vs 1)
        return $old == $new;
    }
}
