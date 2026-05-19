<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $event_id
 * @property bool $is_active
 * @property int|null $order_column
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Hotel|null $hotel
 * @property-read Event|null $event
 *
 * @method static Builder<static>|HotelEvent active()
 * @method static Builder<static>|HotelEvent ordered(string $direction = 'asc')
 * @method static Builder<static>|HotelEvent newModelQuery()
 * @method static Builder<static>|HotelEvent newQuery()
 * @method static Builder<static>|HotelEvent query()
 *
 * @mixin \Eloquent
 */
class HotelEvent extends Model implements Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use LogsActivity;
    use SortableTrait;

    protected $table = 'hotel_event';

    protected $fillable = [
        'hotel_id',
        'event_id',
        'is_active',
        'order_column',
        'notes',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function responseCacheTags(): array
    {
        return ['hotels'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_active', 'notes', 'order_column'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($this->event) {
            $activity->properties = $activity->properties->put('project_id', $this->event->project_id);
        }
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('event_id', $this->event_id);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
