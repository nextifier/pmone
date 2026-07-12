<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Translatable\HasTranslations;

/**
 * A single day of an event. The scanner resolves "today = which day" against
 * these rows, and entry tickets reference them through `valid_days`.
 *
 * @property int $id
 * @property int $event_id
 * @property int $day_number
 * @property Carbon $date
 * @property array<array-key, mixed>|null $label
 * @property bool $is_active
 * @property int|null $order_column
 * @property-read Event|null $event
 *
 * @mixin \Eloquent
 */
class EventDay extends Model implements Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'event_id',
        'day_number',
        'date',
        'label',
        'is_active',
    ];

    public array $translatable = [
        'label',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'day_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The public tickets listing eager-loads validDays (PublicTicketController),
     * so day writes must bust the 'tickets'-tagged cache.
     */
    protected static function responseCacheTags(): array
    {
        return ['tickets'];
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

        static::deleting(function ($model) {
            if ($model->isForceDeleting() === false && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('event_id', $this->event_id);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_event_day');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
