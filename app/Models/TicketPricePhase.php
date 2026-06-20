<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * A time-bound price for a ticket (Pre-registration / Pre-sale / Normal / ...).
 * The active phase is the one whose [starts_at, ends_at] window contains "now";
 * null bounds are treated as open-ended. price = 0 means the phase is free.
 *
 * @property int $id
 * @property int $ticket_id
 * @property string $label
 * @property string $price
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $quota
 * @property int $sold_count
 * @property bool $is_active
 * @property int|null $order_column
 * @property-read Ticket|null $ticket
 *
 * @mixin \Eloquent
 */
class TicketPricePhase extends Model implements Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use SoftDeletes;
    use SortableTrait;

    protected $fillable = [
        'ticket_id',
        'label',
        'price',
        'starts_at',
        'ends_at',
        'quota',
        'is_active',
    ];

    /**
     * @return string[]
     */
    protected static function responseCacheTags(): array
    {
        return ['tickets'];
    }

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'quota' => 'integer',
            'sold_count' => 'integer',
            'is_active' => 'boolean',
        ];
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
        return static::query()->where('ticket_id', $this->ticket_id);
    }

    /**
     * Whether the given moment falls inside this phase's window. Null bounds
     * are treated as -infinity / +infinity respectively.
     */
    public function isActiveAt(Carbon $moment): bool
    {
        if ($this->starts_at !== null && $moment->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $moment->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
