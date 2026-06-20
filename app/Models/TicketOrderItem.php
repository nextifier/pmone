<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A line item of a ticket order: a quantity of one ticket at a snapshotted
 * unit price (and phase label), optionally tied to a chosen add-on session.
 * Produces N attendees.
 *
 * @property int $id
 * @property int $ticket_order_id
 * @property int $ticket_id
 * @property int|null $ticket_session_id
 * @property int $quantity
 * @property string $unit_price
 * @property string|null $phase_label
 * @property string $subtotal
 * @property-read TicketOrder|null $ticketOrder
 * @property-read Ticket|null $ticket
 * @property-read TicketSession|null $ticketSession
 *
 * @mixin \Eloquent
 */
class TicketOrderItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'ticket_order_id',
        'ticket_id',
        'ticket_session_id',
        'selected_event_day_id',
        'quantity',
        'unit_price',
        'phase_label',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
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

    public function ticketOrder(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function ticketSession(): BelongsTo
    {
        return $this->belongsTo(TicketSession::class);
    }

    public function selectedEventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class, 'selected_event_day_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }
}
