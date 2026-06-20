<?php

namespace App\Models;

use App\Enums\Ticketing\AccessCodeKind;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Groups access codes generated together and optionally assigns a quota to a
 * sponsor/exhibitor (e.g. "Gold Sponsor — 20 invitations").
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property string $name
 * @property AccessCodeKind $kind
 * @property string|null $assigned_to
 * @property int|null $brand_id
 * @property string|null $notes
 * @property-read Event|null $event
 * @property-read Brand|null $brand
 * @property-read Collection<int, AccessCode> $accessCodes
 *
 * @mixin \Eloquent
 */
class AccessCodeBatch extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'kind',
        'assigned_to',
        'brand_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AccessCodeKind::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (self $model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'kind', 'assigned_to', 'brand_id', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->loadMissing('event')->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function accessCodes(): HasMany
    {
        return $this->hasMany(AccessCode::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
