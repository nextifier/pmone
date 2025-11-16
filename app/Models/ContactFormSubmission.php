<?php

namespace App\Models;

use App\Enums\ContactFormStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property array<array-key, mixed> $form_data
 * @property string|null $subject
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $followed_up_at
 * @property int|null $followed_up_by
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Project $project
 * @property-read \App\Models\User|null $followedUpByUser
 */
class ContactFormSubmission extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'project_id',
        'form_data',
        'subject',
        'status',
        'ip_address',
        'user_agent',
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
            ->logOnlyDirty();
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
