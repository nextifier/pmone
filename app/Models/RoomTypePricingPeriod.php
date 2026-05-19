<?php

namespace App\Models;

use Database\Factories\RoomTypePricingPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property string $ulid
 * @property int $room_type_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property numeric $rate
 * @property string|null $label
 * @property bool $is_active
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read RoomType|null $roomType
 * @property-read User|null $updater
 *
 * @method static Builder<static>|RoomTypePricingPeriod active()
 * @method static Builder<static>|RoomTypePricingPeriod coveringDate(string $date)
 * @method static \Database\Factories\RoomTypePricingPeriodFactory factory($count = null, $state = [])
 * @method static Builder<static>|RoomTypePricingPeriod newModelQuery()
 * @method static Builder<static>|RoomTypePricingPeriod newQuery()
 * @method static Builder<static>|RoomTypePricingPeriod onlyTrashed()
 * @method static Builder<static>|RoomTypePricingPeriod ordered(string $direction = 'asc')
 * @method static Builder<static>|RoomTypePricingPeriod query()
 * @method static Builder<static>|RoomTypePricingPeriod whereCreatedAt($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereCreatedBy($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereDeletedAt($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereDeletedBy($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereEndDate($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereId($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereIsActive($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereLabel($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereOrderColumn($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereRate($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereRoomTypeId($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereStartDate($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereUlid($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereUpdatedAt($value)
 * @method static Builder<static>|RoomTypePricingPeriod whereUpdatedBy($value)
 * @method static Builder<static>|RoomTypePricingPeriod withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|RoomTypePricingPeriod withoutTrashed()
 *
 * @mixin \Eloquent
 */
class RoomTypePricingPeriod extends Model implements Sortable
{
    /** @use HasFactory<RoomTypePricingPeriodFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'room_type_id',
        'start_date',
        'end_date',
        'rate',
        'label',
        'is_active',
        'order_column',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (auth()->check()) {
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
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['rate', 'start_date', 'end_date', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCoveringDate(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }
}
