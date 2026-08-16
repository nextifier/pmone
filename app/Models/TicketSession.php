<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * A scheduled slot for an add-on ticket (e.g. "4 Oct 12:00-12:15"). An add-on
 * may have zero, one, or many sessions; when more than one the buyer picks one
 * at checkout. Capacity is per-session; null capacity means unlimited.
 *
 * @property int $id
 * @property int $ticket_id
 * @property string $label
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $location
 * @property string|null $host
 * @property int|null $capacity
 * @property int $booked_count
 * @property bool $is_active
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Ticket|null $ticket
 * @property-read Collection<int, TicketOrderItem> $ticketOrderItems
 * @property-read int|null $ticket_order_items_count
 *
 * @method static \Database\Factories\TicketSessionFactory factory($count = null, $state = [])
 * @method static Builder<static>|TicketSession newModelQuery()
 * @method static Builder<static>|TicketSession newQuery()
 * @method static Builder<static>|TicketSession onlyTrashed()
 * @method static Builder<static>|TicketSession ordered(string $direction = 'asc')
 * @method static Builder<static>|TicketSession query()
 * @method static Builder<static>|TicketSession whereBookedCount($value)
 * @method static Builder<static>|TicketSession whereCapacity($value)
 * @method static Builder<static>|TicketSession whereCreatedAt($value)
 * @method static Builder<static>|TicketSession whereCreatedBy($value)
 * @method static Builder<static>|TicketSession whereDeletedAt($value)
 * @method static Builder<static>|TicketSession whereDeletedBy($value)
 * @method static Builder<static>|TicketSession whereEndsAt($value)
 * @method static Builder<static>|TicketSession whereHost($value)
 * @method static Builder<static>|TicketSession whereId($value)
 * @method static Builder<static>|TicketSession whereIsActive($value)
 * @method static Builder<static>|TicketSession whereLabel($value)
 * @method static Builder<static>|TicketSession whereLocation($value)
 * @method static Builder<static>|TicketSession whereOrderColumn($value)
 * @method static Builder<static>|TicketSession whereStartsAt($value)
 * @method static Builder<static>|TicketSession whereTicketId($value)
 * @method static Builder<static>|TicketSession whereUpdatedAt($value)
 * @method static Builder<static>|TicketSession whereUpdatedBy($value)
 * @method static Builder<static>|TicketSession withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TicketSession withoutTrashed()
 *
 * @mixin \Eloquent
 */
class TicketSession extends Model implements Sortable
{
    use ClearsResponseCache;
    use HasFactory;
    use SoftDeletes;
    use SortableTrait;

    /**
     * @return string[]
     */
    protected static function responseCacheTags(): array
    {
        return ['tickets'];
    }

    protected $fillable = [
        'ticket_id',
        'label',
        'starts_at',
        'ends_at',
        'location',
        'host',
        'capacity',
        'is_active',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'booked_count' => 'integer',
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function ticketOrderItems(): HasMany
    {
        return $this->hasMany(TicketOrderItem::class);
    }

    /**
     * Atomically reserve $qty seats of this session's capacity with a single
     * conditional UPDATE, mirroring Ticket::reserve(). Null capacity is
     * unlimited (always succeeds). Returns whether the reservation was
     * granted.
     */
    public function reserve(int $qty): bool
    {
        $reserved = static::query()
            ->whereKey($this->id)
            // Parenthesized - see Ticket::reserve() for why: without the
            // parens, SQL's AND-before-OR precedence would let this clause
            // leak past the whereKey() and match every other roomy session.
            ->whereRaw('(capacity IS NULL OR booked_count + ? <= capacity)', [$qty])
            ->update(['booked_count' => DB::raw('booked_count + '.(int) $qty)]) > 0;

        if ($reserved) {
            $this->clearResponseCacheForRawUpdate();
        }

        return $reserved;
    }

    /**
     * Guarded release of a previously reserved $qty - see Ticket::release().
     */
    public function release(int $qty): void
    {
        $released = static::query()->whereKey($this->id)->where('booked_count', '>=', $qty)->decrement('booked_count', $qty);

        if ($released > 0) {
            $this->clearResponseCacheForRawUpdate();
        }
    }
}
