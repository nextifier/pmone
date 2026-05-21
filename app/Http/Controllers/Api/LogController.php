<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\ProjectPaymentGateway;
use App\Models\PromotionPost;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class LogController extends Controller
{
    use AuthorizesRequests;

    /**
     * Fields too technical or internal to surface in the activity feed
     * (raw IDs, gateway tokens, URLs). Hidden from both descriptions and changes.
     */
    private const HIDDEN_CHANGE_FIELDS = [
        'updated_at',
        'updated_by',
        'created_at',
        'created_by',
        'xendit_invoice_id',
        'xendit_payment_id',
        'xendit_refund_id',
        'payment_url',
        'payment_destination',
        'payment_method',
        'magic_link_token',
    ];

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

        $query = self::eagerLoadActivity(Activity::query())
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

    /**
     * Return all filter options (log names, events, causers) in a single request.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole(['master', 'admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only master and admin roles can access logs.',
            ], 403);
        }

        $logNames = Activity::query()
            ->distinct()
            ->whereNotNull('log_name')
            ->pluck('log_name')
            ->filter()
            ->sort()
            ->values();

        $events = Activity::query()
            ->distinct()
            ->whereNotNull('event')
            ->pluck('event')
            ->filter()
            ->sort()
            ->values();

        $causers = Activity::query()
            ->whereNotNull('causer_id')
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
        if (Activity::query()->whereNull('causer_id')->exists()) {
            $causers->prepend(['id' => 'system', 'name' => 'System']);
        }

        return response()->json([
            'data' => [
                'log_names' => $logNames,
                'events' => $events,
                'causers' => $causers,
            ],
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
     * Eager load the causer and polymorphic subject relations needed to
     * resolve subject names and URLs. morphWith prevents N+1 queries on
     * the nested relations accessed per subject type.
     */
    public static function eagerLoadActivity(Builder $query): Builder
    {
        return $query->with([
            'causer',
            'causer.media',
            'subject' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    BrandEvent::class => ['brand', 'event.project'],
                    Event::class => ['project'],
                    PromotionPost::class => ['brandEvent.brand', 'brandEvent.event.project'],
                    EventProduct::class => ['event.project'],
                    Reservation::class => ['event.project'],
                    Order::class => ['brandEvent.event.project'],
                    ProjectPaymentGateway::class => ['project'],
                ]);
            },
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

            return $attrs['name']
                ?? $attrs['title']
                ?? $attrs['reservation_number']
                ?? $attrs['code']
                ?? null;
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

        // Reservation: identified by its booking reference number
        if ($subjectType === 'Reservation') {
            return $subject->reservation_number;
        }

        // PromoCode: the code string is the identifier
        if ($subjectType === 'PromoCode') {
            return $subject->code;
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
            'Brand' => $subject->slug ? "/brands/{$subject->slug}/edit" : null,
            'User' => $subject->username ? "/users?search={$subject->username}" : null,
            'BrandEvent' => self::getBrandEventUrl($subject),
            'Event' => self::getEventUrl($subject),
            'Contact' => $subject->ulid ? "/contacts?open={$subject->ulid}" : null,
            'ContactFormSubmission' => $subject->ulid ? "/inbox?open={$subject->ulid}" : null,
            'ShortLink' => $subject->slug ? "/links/{$subject->slug}" : null,
            'PromotionPost' => self::getPromotionPostUrl($subject),
            'LinkPage' => $subject->slug ? "/link-pages/{$subject->slug}" : null,
            'Task' => '/tasks',
            'Project' => $subject->username ? "/projects/{$subject->username}" : null,
            'EventProduct' => self::getEventProductUrl($subject),
            'Reservation' => self::getReservationUrl($subject),
            'Order' => self::getOrderUrl($subject),
            'Announcement' => $subject->id ? "/announcements/{$subject->id}/edit" : null,
            'Partner' => $subject->slug ? "/partners/{$subject->slug}/edit" : null,
            'PromotionRule' => $subject->ulid ? "/promotion-rules/{$subject->ulid}/show" : null,
            'PromoCode' => $subject->ulid ? "/promo-codes/{$subject->ulid}/show" : null,
            'ProjectPaymentGateway' => self::getPaymentGatewayUrl($subject),
            'Hotel' => $subject->slug ? "/hotels?search={$subject->slug}" : null,
            'GaProperty' => '/web-analytics',
            default => null,
        };
    }

    private static function getReservationUrl(mixed $subject): ?string
    {
        $event = $subject->event;
        $project = $event?->project;
        if (! $event?->slug || ! $project?->username || ! $subject->ulid) {
            return null;
        }

        return "/projects/{$project->username}/events/{$event->slug}/reservations/{$subject->ulid}";
    }

    private static function getOrderUrl(mixed $subject): ?string
    {
        $event = $subject->brandEvent?->event;
        $project = $event?->project;
        if (! $event?->slug || ! $project?->username || ! $subject->ulid) {
            return null;
        }

        return "/projects/{$project->username}/events/{$event->slug}/operational/orders/{$subject->ulid}";
    }

    private static function getPaymentGatewayUrl(mixed $subject): ?string
    {
        $project = $subject->project;
        if (! $project?->username) {
            return null;
        }

        return "/projects/{$project->username}/settings/payment-gateways";
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
                return $description
                    ? "{$userName} ".lcfirst($description)
                    : "{$userName} imported data";

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
                $count = $activity->properties['count'] ?? null;
                $countLabel = is_numeric($count)
                    ? ' ('.number_format((int) $count).' '.((int) $count === 1 ? 'row' : 'rows').')'
                    : '';

                if ($description) {
                    return "{$userName} ".lcfirst($description).$countLabel;
                }

                $modelType = $activity->properties['model_type'] ?? 'data';

                return "{$userName} exported {$modelType}{$countLabel}";

            case 'api_key_regenerated':
                $consumerName = $activity->properties['consumer_name'] ?? $subjectName ?? 'unknown';

                return "{$userName} regenerated API key for \"{$consumerName}\"";

            case 'payment_paid':
                $amount = $activity->properties['amount'] ?? null;
                $amountLabel = $amount ? ' ('.self::formatCurrency($amount).')' : '';

                return $subjectName
                    ? "Payment received for reservation \"{$subjectName}\"{$amountLabel}"
                    : "Payment received via Xendit{$amountLabel}";

            case 'payment_expired':
                return $subjectName
                    ? "Xendit invoice expired for reservation \"{$subjectName}\""
                    : 'Xendit invoice expired';

            case 'refund_initiated':
                $amount = $activity->properties['refund_amount'] ?? null;
                $amountLabel = $amount ? ' ('.self::formatCurrency($amount).')' : '';

                return $subjectName
                    ? "Refund initiated for reservation \"{$subjectName}\"{$amountLabel}"
                    : "Refund initiated via Xendit{$amountLabel}";

            case 'refund_settled':
                return $subjectName
                    ? "Refund settled for reservation \"{$subjectName}\""
                    : 'Xendit refund settled';

            case 'refund_failed':
                $reason = $activity->properties['failure_reason'] ?? $activity->properties['error'] ?? null;
                $reasonLabel = $reason ? " - {$reason}" : '';

                return $subjectName
                    ? "Refund failed for reservation \"{$subjectName}\"{$reasonLabel}"
                    : "Xendit refund failed{$reasonLabel}";

            case 'auto_expired':
                return $subjectName
                    ? "Reservation \"{$subjectName}\" auto-expired (unpaid)"
                    : 'Reservation auto-expired (unpaid)';

            case 'allotments_released':
                $count = $activity->properties['released_count'] ?? 0;

                return "Released {$count} expired allotment(s)";

            case 'adjustment_applied':
                $kind = $activity->properties['kind'] ?? 'adjustment';
                $amount = $activity->properties['amount'] ?? null;
                $amountLabel = $amount ? ' ('.self::formatCurrency($amount).')' : '';
                $modelLabel = $subjectType ? self::humanizeModelName($subjectType) : 'record';

                return $subjectName
                    ? "{$userName} applied {$kind} on {$modelLabel} \"{$subjectName}\"{$amountLabel}"
                    : "{$userName} applied {$kind}{$amountLabel}";

            case 'adjustment_voided':
                $kind = $activity->properties['kind'] ?? 'adjustment';
                $modelLabel = $subjectType ? self::humanizeModelName($subjectType) : 'record';

                return $subjectName
                    ? "{$userName} voided {$kind} on {$modelLabel} \"{$subjectName}\""
                    : "{$userName} voided {$kind}";

            case 'media_deleted':
                $fileName = $activity->properties['file_name'] ?? 'media';

                return "{$userName} deleted media \"{$fileName}\"";

            case 'payment_gateway_added':
                $provider = $activity->properties['provider'] ?? 'gateway';
                $mode = $activity->properties['mode'] ?? null;
                $modeLabel = $mode ? " ({$mode})" : '';

                return "{$userName} added payment gateway: {$provider}{$modeLabel}";

            case 'payment_gateway_removed':
                $provider = $activity->properties['provider'] ?? 'gateway';
                $mode = $activity->properties['mode'] ?? null;
                $modeLabel = $mode ? " ({$mode})" : '';

                return "{$userName} removed payment gateway: {$provider}{$modeLabel}";

            case 'payment_gateway_credentials_rotated':
                $provider = $activity->properties['provider'] ?? 'gateway';
                $fields = $activity->properties['rotated_fields'] ?? [];
                $fieldsLabel = is_array($fields) && ! empty($fields) ? ' ('.implode(', ', $fields).')' : '';

                return "{$userName} rotated credentials for {$provider}{$fieldsLabel}";

            case 'analytics_synced':
                $count = $activity->properties['properties_count'] ?? null;
                if ($count !== null) {
                    return "{$userName} triggered analytics sync for {$count} property(ies)";
                }

                return $subjectName
                    ? "{$userName} triggered analytics sync for \"{$subjectName}\""
                    : "{$userName} triggered analytics sync";

            case 'event_linked':
                $linkedTitle = $activity->properties['linked_event_title'] ?? 'an event';

                return $subjectName
                    ? "{$userName} linked \"{$linkedTitle}\" as conjunction to \"{$subjectName}\""
                    : "{$userName} linked conjunction event \"{$linkedTitle}\"";

            case 'reservation_cancelled':
                $reason = $activity->properties['reason'] ?? null;
                $reasonLabel = $reason ? " - {$reason}" : '';

                return $subjectName
                    ? "{$userName} cancelled reservation \"{$subjectName}\"{$reasonLabel}"
                    : "{$userName} cancelled a reservation{$reasonLabel}";

            case 'voucher_sent':
                return $subjectName
                    ? "{$userName} sent hotel voucher for \"{$subjectName}\""
                    : "{$userName} sent hotel voucher";

            case 'event_unlinked':
                $linkedTitle = $activity->properties['linked_event_title'] ?? 'an event';

                return $subjectName
                    ? "{$userName} unlinked \"{$linkedTitle}\" from \"{$subjectName}\""
                    : "{$userName} unlinked conjunction event \"{$linkedTitle}\"";

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
                $aliases = [
                    'Magic link requested' => 'requested a magic link',
                    'Password reset' => 'reset their password',
                    'Password changed' => 'changed their password',
                    'Activity logs cleared' => 'cleared all activity logs',
                ];

                if ($description && isset($aliases[$description])) {
                    return "{$userName} {$aliases[$description]}";
                }

                if ($description) {
                    $clean = preg_replace('/^User\s+/i', '', $description);

                    return "{$userName} ".lcfirst($clean);
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

        // array_values re-indexes so the count === 1 branch can safely use $labels[0].
        $fields = array_values(
            array_filter($fields, fn ($f) => ! in_array($f, self::HIDDEN_CHANGE_FIELDS, true))
        );

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
            'payment_gateway_id' => 'payment gateway',
            'payment_channel' => 'payment method',
            'promo_code_applied' => 'promo code',
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
            if (in_array($field, self::HIDDEN_CHANGE_FIELDS, true)) {
                continue;
            }

            $oldValue = $old[$field] ?? null;

            // Skip changes where old and new are effectively the same
            if (self::isEffectivelySame($oldValue, $newValue)) {
                continue;
            }

            // Resolve the payment gateway to its owning project name; a raw id is meaningless.
            if ($field === 'payment_gateway_id') {
                $projectName = $activity->subject?->event?->project?->name;
                if ($projectName) {
                    $changes[] = [
                        'field' => self::humanizeFieldName($field),
                        'old' => null,
                        'new' => $projectName,
                    ];
                }

                continue;
            }

            $isCurrency = self::isCurrencyField($field);
            $changes[] = [
                'field' => self::humanizeFieldName($field),
                'old' => $isCurrency ? self::formatCurrencyValue($oldValue) : $oldValue,
                'new' => $isCurrency ? self::formatCurrencyValue($newValue) : $newValue,
            ];
        }

        // For custom events without attributes/old structure, show relevant properties
        if (empty($changes) && ! isset($properties['attributes'])) {
            $skip = array_merge(['project_id'], self::HIDDEN_CHANGE_FIELDS);
            foreach ($properties->toArray() as $key => $value) {
                if (in_array($key, $skip, true)) {
                    continue;
                }
                // Skip null/empty values for custom events too
                if ($value === null || $value === '') {
                    continue;
                }
                $changes[] = [
                    'field' => self::humanizeFieldName($key),
                    'old' => null,
                    'new' => self::isCurrencyField($key) ? self::formatCurrencyValue($value) : $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Format a numeric amount as Indonesian Rupiah (e.g. "Rp9.952.250").
     */
    private static function formatCurrency(int|float|string $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Format a value as currency when numeric; otherwise return it unchanged
     * so null old-values stay null for the frontend's "added" rendering.
     */
    private static function formatCurrencyValue(mixed $value): mixed
    {
        return is_numeric($value) ? self::formatCurrency($value) : $value;
    }

    /**
     * Determine whether a change field holds a monetary (Rupiah) value.
     * Uses an explicit allowlist plus safe suffixes; percentage/rate and
     * count fields are intentionally excluded.
     */
    private static function isCurrencyField(string $field): bool
    {
        $field = strtolower($field);

        $exact = [
            'amount', 'amount_discounted', 'base_rate', 'base_rate_override',
            'price', 'rate_per_night', 'subtotal', 'subtotal_rooms',
            'subtotal_transfer', 'total',
        ];

        if (in_array($field, $exact, true)) {
            return true;
        }

        foreach (['_amount', '_price', '_total'] as $suffix) {
            if (str_ends_with($field, $suffix)) {
                return true;
            }
        }

        return false;
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
