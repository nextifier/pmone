<?php

namespace App\Models;

use App\Enums\Ticketing\TicketWaitlistEntryStatus;
use Database\Factories\TicketWaitlistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A buyer's spot in the FIFO queue for a sold-out ticket. Created by
 * WaitlistService::join(); consumed by offerReleasedSeats() -> claim() once
 * a seat frees up. `ticket_id` is nullable for a future event-level waitlist
 * (Plan 021) - every entry this plan creates always has a concrete ticket.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $ticket_id
 * @property string $email
 * @property string|null $name
 * @property string|null $phone
 * @property int $quantity
 * @property TicketWaitlistEntryStatus $status
 * @property int $position
 * @property Carbon|null $offered_at
 * @property Carbon|null $offer_expires_at
 * @property string|null $claim_token
 * @property-read Event|null $event
 * @property-read Ticket|null $ticket
 *
 * @mixin \Eloquent
 */
class TicketWaitlistEntry extends Model
{
    /** @use HasFactory<TicketWaitlistEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'ticket_id',
        'email',
        'name',
        'phone',
        'quantity',
        'status',
        'position',
        'offered_at',
        'offer_expires_at',
        'claim_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketWaitlistEntryStatus::class,
            'quantity' => 'integer',
            'position' => 'integer',
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (! empty($model->email)) {
                $model->email = strtolower(trim($model->email));
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('email') && ! empty($model->email)) {
                $model->email = strtolower(trim($model->email));
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Whether this entry currently holds a live, unexpired claim offer.
     */
    public function hasActiveOffer(): bool
    {
        return $this->status === TicketWaitlistEntryStatus::Offered
            && $this->offer_expires_at !== null
            && $this->offer_expires_at->isFuture();
    }

    /**
     * Generate a fresh opaque claim token (URL-safe, unguessable). Regenerated
     * on every new offer so a stale/expired link can never be replayed to
     * claim a later re-offer of the same entry.
     */
    public static function generateClaimToken(): string
    {
        return (string) Str::random(48);
    }
}
