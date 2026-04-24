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
 * @property int $id
 * @property string $ulid
 * @property int $hotel_id
 * @property int $room_type_id
 * @property int $quantity
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property \Illuminate\Support\Carbon|null $release_at
 * @property string|null $surcharge_type
 * @property numeric|null $surcharge_amount
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property array<array-key, mixed>|null $settings
 * @property array<array-key, mixed>|null $more_details
 * @property int|null $order_column
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $deleter
 * @property-read \App\Models\Hotel|null $hotel
 * @property-read \App\Models\RoomType|null $roomType
 * @property-read \App\Models\User|null $updater
 * @method static Builder<static>|HotelEventAllotment active()
 * @method static \Database\Factories\HotelEventAllotmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|HotelEventAllotment newModelQuery()
 * @method static Builder<static>|HotelEventAllotment newQuery()
 * @method static Builder<static>|HotelEventAllotment onlyTrashed()
 * @method static Builder<static>|HotelEventAllotment ordered(string $direction = 'asc')
 * @method static Builder<static>|HotelEventAllotment overlapsRange(string $checkIn, string $checkOut)
 * @method static Builder<static>|HotelEventAllotment query()
 * @method static Builder<static>|HotelEventAllotment whereCreatedAt($value)
 * @method static Builder<static>|HotelEventAllotment whereCreatedBy($value)
 * @method static Builder<static>|HotelEventAllotment whereDeletedAt($value)
 * @method static Builder<static>|HotelEventAllotment whereDeletedBy($value)
 * @method static Builder<static>|HotelEventAllotment whereEndDate($value)
 * @method static Builder<static>|HotelEventAllotment whereHotelId($value)
 * @method static Builder<static>|HotelEventAllotment whereId($value)
 * @method static Builder<static>|HotelEventAllotment whereIsActive($value)
 * @method static Builder<static>|HotelEventAllotment whereMoreDetails($value)
 * @method static Builder<static>|HotelEventAllotment whereOrderColumn($value)
 * @method static Builder<static>|HotelEventAllotment whereQuantity($value)
 * @method static Builder<static>|HotelEventAllotment whereReleaseAt($value)
 * @method static Builder<static>|HotelEventAllotment whereRoomTypeId($value)
 * @method static Builder<static>|HotelEventAllotment whereSettings($value)
 * @method static Builder<static>|HotelEventAllotment whereStartDate($value)
 * @method static Builder<static>|HotelEventAllotment whereSurchargeAmount($value)
 * @method static Builder<static>|HotelEventAllotment whereSurchargeType($value)
 * @method static Builder<static>|HotelEventAllotment whereUlid($value)
 * @method static Builder<static>|HotelEventAllotment whereUpdatedAt($value)
 * @method static Builder<static>|HotelEventAllotment whereUpdatedBy($value)
 * @method static Builder<static>|HotelEventAllotment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|HotelEventAllotment withoutTrashed()
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
