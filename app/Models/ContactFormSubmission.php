<?php

namespace App\Models;

use App\Enums\ContactFormStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property array<array-key, mixed> $form_data
 * @property string|null $subject
 * @property ContactFormStatus $status
 * @property \Illuminate\Support\Carbon|null $followed_up_at
 * @property int|null $followed_up_by
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $deleter
 * @property-read \App\Models\User|null $followedUpByUser
 * @property-read \App\Models\Project $project
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission byStatus(string $status)
 * @method static \Database\Factories\ContactFormSubmissionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission forProject(int $projectId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission new()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission unfollowedUp()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereFollowedUpAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereFollowedUpBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereFormData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactFormSubmission withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ContactFormSubmission extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'form_data',
        'subject',
        'status',
        'ip_address',
        'user_agent',
        'followed_up_at',
        'followed_up_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'followed_up_at' => 'datetime',
            'status' => ContactFormStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'followed_up_at', 'followed_up_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Models\Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->put('project_id', $this->project_id);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function followedUpByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_up_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNew($query)
    {
        return $query->where('status', ContactFormStatus::New->value);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeUnfollowedUp($query)
    {
        return $query->whereNull('followed_up_at');
    }

    public function markAsFollowedUp(?int $userId = null): void
    {
        $this->update([
            'followed_up_at' => now(),
            'followed_up_by' => $userId ?? auth()->id(),
        ]);
    }
}
