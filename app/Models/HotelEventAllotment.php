<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read User|null $creator
 * @property-read User|null $deleter
 * @property-read Event|null $event
 * @property-read Hotel|null $hotel
 * @property-read RoomType|null $roomType
 * @property-read User|null $updater
 *
 * @method static Builder<static>|HotelEventAllotment active()
 * @method static \Database\Factories\HotelEventAllotmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|HotelEventAllotment newModelQuery()
 * @method static Builder<static>|HotelEventAllotment newQuery()
 * @method static Builder<static>|HotelEventAllotment onlyTrashed()
 * @method static Builder<static>|HotelEventAllotment overlapsRange(string $checkIn, string $checkOut)
 * @method static Builder<static>|HotelEventAllotment query()
 * @method static Builder<static>|HotelEventAllotment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|HotelEventAllotment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class HotelEventAllotment extends Model implements Sortable
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'quantity',
        'start_date',
        'end_date',
        'release_at',
        'surcharge_type',
        'surcharge_amount',
        'is_active',
        'settings',
        'more_details',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'release_at' => 'datetime',
            'surcharge_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'settings' => 'array',
            'more_details' => 'array',
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
            ->logOnly(['quantity', 'start_date', 'end_date', 'release_at', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
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

    public function scopeOverlapsRange(Builder $query, string $checkIn, string $checkOut): Builder
    {
        return $query->where('start_date', '<=', $checkIn)
            ->where('end_date', '>=', $checkOut);
    }
}
