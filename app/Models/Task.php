<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property string $title
 * @property string|null $description
 * @property string|null $status
 * @property string|null $priority
 * @property string|null $complexity
 * @property string $visibility
 * @property int|null $project_id
 * @property int|null $assignee_id
 * @property \Illuminate\Support\Carbon|null $estimated_start_at
 * @property \Illuminate\Support\Carbon|null $estimated_completion_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int $order_column
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $assignee
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\Project|null $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $sharedUsers
 * @property-read int|null $shared_users_count
 * @property-read \App\Models\User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task byComplexity(array|string $complexity)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task byPriority(array|string $priority)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task byStatus(array|string $status)
 * @method static \Database\Factories\TaskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task overdue()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task search(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task upcoming(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task visibleTo(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereAssigneeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereComplexity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereEstimatedCompletionAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereEstimatedStartAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Task extends Model implements HasMedia
{
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'title',
        'description',
        'status',
        'priority',
        'complexity',
        'visibility',
        'project_id',
        'assignee_id',
        'estimated_start_at',
        'estimated_completion_at',
        'completed_at',
        'order_column',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_start_at' => 'datetime',
            'estimated_completion_at' => 'datetime',
            'completed_at' => 'datetime',
            'order_column' => 'integer',
        ];
    }

    // Application-level "enums" - constants for validation
    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const COMPLEXITY_LOW = 'low';

    public const COMPLEXITY_MEDIUM = 'medium';

    public const COMPLEXITY_HIGH = 'high';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    public const SHARED_ROLE_VIEWER = 'viewer';

    public const SHARED_ROLE_EDITOR = 'editor';

    // Helper methods to get allowed values
    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_TODO,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ARCHIVED,
        ];
    }

    public static function allowedPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
        ];
    }

    public static function allowedComplexities(): array
    {
        return [
            self::COMPLEXITY_LOW,
            self::COMPLEXITY_MEDIUM,
            self::COMPLEXITY_HIGH,
        ];
    }

    public static function allowedVisibilities(): array
    {
        return [
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_PRIVATE,
            self::VISIBILITY_SHARED,
        ];
    }

    public static function allowedSharedRoles(): array
    {
        return [
            self::SHARED_ROLE_VIEWER,
            self::SHARED_ROLE_EDITOR,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            // Auto-set completed_at when status is completed
            if ($model->status === self::STATUS_COMPLETED && empty($model->completed_at)) {
                $model->completed_at = now();
            }

            // Only set created_by if not already set and user is authenticated
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }

            // Auto-set order_column to max + 1 so new tasks appear at the bottom
            if (empty($model->order_column)) {
                $model->order_column = (int) static::max('order_column') + 1;
            }
        });

        static::updating(function ($model) {
            // Auto-set completed_at when status changes to completed
            if ($model->isDirty('status') && $model->status === self::STATUS_COMPLETED && empty($model->completed_at)) {
                $model->completed_at = now();
            }

            // Clear completed_at if status changes from completed to something else
            if ($model->isDirty('status') && $model->getOriginal('status') === self::STATUS_COMPLETED && $model->status !== self::STATUS_COMPLETED) {
                $model->completed_at = null;
            }

            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }

            // Auto-cleanup media when task is force deleted
            if ($model->isForceDeleting()) {
                foreach (array_keys($model->getMediaCollections()) as $collectionName) {
                    $model->clearMediaCollection($collectionName);
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'priority', 'complexity', 'visibility', 'assignee_id', 'estimated_start_at', 'estimated_completion_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Models\Activity $activity, string $eventName): void
    {
        if ($this->project_id) {
            $activity->properties = $activity->properties->put('project_id', $this->project_id);
        }
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        // Description images conversions (maintain aspect ratio, no crop)
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('description_images')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('description_images')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('description_images');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('description_images');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('description_images');
    }

    public function getMediaCollections(): array
    {
        return [
            'description_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480, // 20MB per image
            ],
        ];
    }

    /**
     * Audit trail relationships
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Task assignment relationship
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Project relationship (optional)
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Shared users (many-to-many with pivot data)
     */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Scope: Tasks visible to a specific user
     */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', self::VISIBILITY_PUBLIC)
                ->orWhere('created_by', $user->id)
                ->orWhere('assignee_id', $user->id)
                ->orWhereHas('sharedUsers', fn ($u) => $u->where('user_id', $user->id))
                ->orWhereHas('project.members', fn ($m) => $m->where('user_id', $user->id));
        });
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string|array $status)
    {
        return $query->whereIn('status', is_array($status) ? $status : [$status]);
    }

    /**
     * Scope: Filter by priority
     */
    public function scopeByPriority($query, string|array $priority)
    {
        return $query->whereIn('priority', is_array($priority) ? $priority : [$priority]);
    }

    /**
     * Scope: Filter by complexity
     */
    public function scopeByComplexity($query, string|array $complexity)
    {
        return $query->whereIn('complexity', is_array($complexity) ? $complexity : [$complexity]);
    }

    /**
     * Scope: Overdue tasks
     */
    public function scopeOverdue($query)
    {
        return $query->where('estimated_completion_at', '<', now())
            ->where('status', '!=', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Upcoming tasks (starting within N days)
     */
    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->whereBetween('estimated_start_at', [now(), now()->addDays($days)])
            ->where('status', self::STATUS_TODO);
    }

    /**
     * Scope: Search tasks by title or description
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%");
        });
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->estimated_completion_at &&
            $this->estimated_completion_at->isPast() &&
            $this->status !== self::STATUS_COMPLETED;
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Mark task as completed
     */
    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark task as incomplete (revert from completed)
     */
    public function incomplete(): void
    {
        $this->update([
            'status' => self::STATUS_TODO,
            'completed_at' => null,
        ]);
    }
}
