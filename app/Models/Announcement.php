<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $ulid
 * @property string $title
 * @property string|null $description
 * @property string|null $icon
 * @property string $type
 * @property string $status
 * @property bool $is_global
 * @property array<array-key, mixed>|null $target_roles
 * @property array<array-key, mixed>|null $cta_actions
 * @property array<array-key, mixed>|null $more_details
 * @property array<array-key, mixed>|null $settings
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property bool $is_dismissible
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Collection<int, AnnouncementUserDismissal> $dismissals
 * @property-read int|null $dismissals_count
 * @property-read Collection<int, Event> $events
 * @property-read int|null $events_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Project> $projects
 * @property-read int|null $projects_count
 * @property-read User|null $updater
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static Builder<static>|Announcement active()
 * @method static \Database\Factories\AnnouncementFactory factory($count = null, $state = [])
 * @method static Builder<static>|Announcement newModelQuery()
 * @method static Builder<static>|Announcement newQuery()
 * @method static Builder<static>|Announcement notDismissedBy(?\App\Models\User $user)
 * @method static Builder<static>|Announcement onlyTrashed()
 * @method static Builder<static>|Announcement published()
 * @method static Builder<static>|Announcement query()
 * @method static Builder<static>|Announcement visibleTo(?\App\Models\User $user)
 * @method static Builder<static>|Announcement whereCreatedAt($value)
 * @method static Builder<static>|Announcement whereCreatedBy($value)
 * @method static Builder<static>|Announcement whereCtaActions($value)
 * @method static Builder<static>|Announcement whereDeletedAt($value)
 * @method static Builder<static>|Announcement whereDeletedBy($value)
 * @method static Builder<static>|Announcement whereDescription($value)
 * @method static Builder<static>|Announcement whereEndTime($value)
 * @method static Builder<static>|Announcement whereIcon($value)
 * @method static Builder<static>|Announcement whereId($value)
 * @method static Builder<static>|Announcement whereIsDismissible($value)
 * @method static Builder<static>|Announcement whereIsGlobal($value)
 * @method static Builder<static>|Announcement whereMoreDetails($value)
 * @method static Builder<static>|Announcement whereOrderColumn($value)
 * @method static Builder<static>|Announcement whereSettings($value)
 * @method static Builder<static>|Announcement whereStartTime($value)
 * @method static Builder<static>|Announcement whereStatus($value)
 * @method static Builder<static>|Announcement whereTargetRoles($value)
 * @method static Builder<static>|Announcement whereTitle($value)
 * @method static Builder<static>|Announcement whereType($value)
 * @method static Builder<static>|Announcement whereUlid($value)
 * @method static Builder<static>|Announcement whereUpdatedAt($value)
 * @method static Builder<static>|Announcement whereUpdatedBy($value)
 * @method static Builder<static>|Announcement withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Announcement withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Announcement extends Model implements HasMedia
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
        'icon',
        'type',
        'status',
        'is_global',
        'target_roles',
        'cta_actions',
        'more_details',
        'settings',
        'start_time',
        'end_time',
        'is_dismissible',
        'order_column',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'is_dismissible' => 'boolean',
            'target_roles' => 'array',
            'cta_actions' => 'array',
            'more_details' => 'array',
            'settings' => 'array',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'order_column' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }

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
            ->logOnly(['title', 'type', 'status', 'is_global', 'is_dismissible', 'start_time', 'end_time'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('image')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('image')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('image');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('image');
    }

    public function getMediaCollections(): array
    {
        return [
            'image' => [
                'single_file' => true,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
                'max_size' => 20480,
            ],
        ];
    }

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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_user')
            ->withTimestamps();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'announcement_event')
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'announcement_project')
            ->withTimestamps();
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementUserDismissal::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('start_time')->orWhere('start_time', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_time')->orWhere('end_time', '>=', $now);
            });
    }

    public function scopeNotDismissedBy(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        return $query->whereDoesntHave('dismissals', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * Returns announcements that should be visible to the given user.
     *
     * Match if any one is true:
     *  - announcement is global
     *  - user has any role in target_roles
     *  - user is in the targeted users list
     *  - user is a member of any project containing a targeted event
     *  - user is a member of any targeted project
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $query->published()->active();

        if (! $user) {
            return $query->where('is_global', true);
        }

        $userRoleNames = $user->getRoleNames()->all();
        $userProjectIds = $user->projects()->pluck('projects.id')->all();

        return $query
            ->notDismissedBy($user)
            ->where(function (Builder $q) use ($user, $userRoleNames, $userProjectIds) {
                $q->where('is_global', true);

                if (! empty($userRoleNames)) {
                    $q->orWhere(function ($q2) use ($userRoleNames) {
                        foreach ($userRoleNames as $role) {
                            $q2->orWhereJsonContains('target_roles', $role);
                        }
                    });
                }

                $q->orWhereHas('users', function ($q2) use ($user) {
                    $q2->where('users.id', $user->id);
                });

                if (! empty($userProjectIds)) {
                    $q->orWhereHas('projects', function ($q2) use ($userProjectIds) {
                        $q2->whereIn('projects.id', $userProjectIds);
                    });

                    $q->orWhereHas('events', function ($q2) use ($userProjectIds) {
                        $q2->whereIn('events.project_id', $userProjectIds);
                    });
                }
            });
    }
}
