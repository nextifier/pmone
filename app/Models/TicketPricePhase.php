<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * A time-bound price for a ticket (Pre-registration / Pre-sale / Normal / .
 *
 * ..).
 * The active phase is the one whose [starts_at, ends_at] window contains "now";
 * null bounds are treated as open-ended. price = 0 means the phase is free.
 *
 * @property int $id
 * @property int $ticket_id
 * @property string $label
 * @property numeric $price
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $quota
 * @property int $sold_count
 * @property bool $is_active
 * @property int|null $order_column
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Ticket|null $ticket
 *
 * @method static \Database\Factories\TicketPricePhaseFactory factory($count = null, $state = [])
 * @method static Builder<static>|TicketPricePhase newModelQuery()
 * @method static Builder<static>|TicketPricePhase newQuery()
 * @method static Builder<static>|TicketPricePhase onlyTrashed()
 * @method static Builder<static>|TicketPricePhase ordered(string $direction = 'asc')
 * @method static Builder<static>|TicketPricePhase query()
 * @method static Builder<static>|TicketPricePhase whereCreatedAt($value)
 * @method static Builder<static>|TicketPricePhase whereCreatedBy($value)
 * @method static Builder<static>|TicketPricePhase whereDeletedAt($value)
 * @method static Builder<static>|TicketPricePhase whereDeletedBy($value)
 * @method static Builder<static>|TicketPricePhase whereEndsAt($value)
 * @method static Builder<static>|TicketPricePhase whereId($value)
 * @method static Builder<static>|TicketPricePhase whereIsActive($value)
 * @method static Builder<static>|TicketPricePhase whereLabel($value)
 * @method static Builder<static>|TicketPricePhase whereOrderColumn($value)
 * @method static Builder<static>|TicketPricePhase wherePrice($value)
 * @method static Builder<static>|TicketPricePhase whereQuota($value)
 * @method static Builder<static>|TicketPricePhase whereSoldCount($value)
 * @method static Builder<static>|TicketPricePhase whereStartsAt($value)
 * @method static Builder<static>|TicketPricePhase whereTicketId($value)
 * @method static Builder<static>|TicketPricePhase whereUpdatedAt($value)
 * @method static Builder<static>|TicketPricePhase whereUpdatedBy($value)
 * @method static Builder<static>|TicketPricePhase withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TicketPricePhase withoutTrashed()
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

    /**
     * Whether this phase still has room to sell $qty more at its price.
     * A null quota means uncapped.
     */
    public function hasCapacityFor(int $qty): bool
    {
        return $this->quota === null || ($this->quota - $this->sold_count) >= $qty;
    }

    /**
     * Whether this phase has reached its quota (always false when uncapped).
     */
    public function isSoldOut(): bool
    {
        return $this->quota !== null && $this->sold_count >= $this->quota;
    }

    /**
     * Atomically reserve $qty units of this phase's quota with a single
     * conditional UPDATE, mirroring Ticket::reserve(). Null quota is
     * unlimited (always succeeds). Returns whether the reservation was
     * granted.
     */
    public function reserve(int $qty): bool
    {
        $reserved = static::query()
            ->whereKey($this->id)
            // Parenthesized - see Ticket::reserve() for why: without the
            // parens, SQL's AND-before-OR precedence would let this clause
            // leak past the whereKey() and match every other roomy phase.
            ->whereRaw('(quota IS NULL OR sold_count + ? <= quota)', [$qty])
            ->update(['sold_count' => DB::raw('sold_count + '.(int) $qty)]) > 0;

        if ($reserved) {
            $this->clearResponseCacheForRawUpdate();
        }

        return $reserved;
    }

    /**
     * Admin-preview only: increment sold_count unconditionally, skipping the
     * quota guard in reserve().
     *
     * Staff testing checkout on the live site need to get past a phase whose
     * early-bird quota is exhausted while the ticket itself still has stock -
     * the case the website labels "sold out" even though there are seats. The
     * counter is still incremented (rather than the reservation being skipped)
     * so phase reporting stays truthful and release() can undo it. Ticket
     * stock and event capacity are NOT bypassed anywhere.
     */
    public function reserveIgnoringQuota(int $qty): void
    {
        static::query()
            ->whereKey($this->id)
            ->update(['sold_count' => DB::raw('sold_count + '.(int) $qty)]);

        $this->clearResponseCacheForRawUpdate();
    }

    /**
     * Guarded release of a previously reserved $qty - see Ticket::release().
     */
    public function release(int $qty): void
    {
        $released = static::query()->whereKey($this->id)->where('sold_count', '>=', $qty)->decrement('sold_count', $qty);

        if ($released > 0) {
            $this->clearResponseCacheForRawUpdate();
        }
    }
}
