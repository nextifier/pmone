<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A physical ticket / QR holder. One per attendee across all line items, each
 * with its own opaque `qr_token` (one token for e-ticket and badge label) and
 * its own check-in state. Names start as placeholders ("Tamu #n") and can be
 * personalized or claimed by a user later.
 *
 * @property int $id
 * @property string $ulid
 * @property int $ticket_order_item_id
 * @property int $ticket_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $qr_token
 * @property int|null $claimed_by_user_id
 * @property Carbon|null $personalized_at
 * @property Carbon|null $checked_in_at
 * @property int|null $checked_in_by
 * @property int|null $checkin_event_id
 * @property int $reprint_count
 * @property-read TicketOrderItem|null $ticketOrderItem
 * @property-read Ticket|null $ticket
 *
 * @mixin \Eloquent
 */
class Attendee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'ticket_order_item_id',
        'ticket_id',
        'name',
        'email',
        'phone',
        'qr_token',
        'claimed_by_user_id',
        'personalized_at',
        'checked_in_at',
        'checked_in_by',
        'checkin_event_id',
        'reprint_count',
    ];

    protected function casts(): array
    {
        return [
            'personalized_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'reprint_count' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Attendee $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->qr_token)) {
                $model->qr_token = (string) Str::ulid();
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

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function isPersonalized(): bool
    {
        return $this->personalized_at !== null;
    }

    /**
     * Resolve the personal account this ticket can sign into, keyed on the
     * attendee's own email (the email itself proves ownership). This covers a
     * returning visitor with a pre-existing account - not only first-time
     * buyers. Returns null for elevated (staff/admin/exhibitor) or inactive
     * accounts, which must never get a passwordless one-click session.
     */
    public function resolveLoginableUser(): ?User
    {
        if (! $this->email) {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($this->email))])->first();

        if (! $user || $user->status === 'inactive') {
            return null;
        }

        return $user->hasAnyRole(['master', 'admin', 'staff', 'exhibitor']) ? null : $user;
    }

    /**
     * Secret token for the e-ticket email's "Go to dashboard" button. It is an
     * HMAC of the ulid + email, so it is only derivable server-side and travels
     * ONLY inside the email - the shareable e-ticket page URL never carries it.
     * That keeps one-click sign-in to the email recipient, even when the buyer
     * used someone else's email.
     */
    public function dashboardLoginToken(): ?string
    {
        if (! $this->email) {
            return null;
        }

        return hash_hmac('sha256', $this->ulid.'|'.mb_strtolower($this->email), (string) config('app.key'));
    }

    /**
     * Scope attendees to a single event through the order chain
     * (attendee -> ticketOrderItem -> ticketOrder.event_id).
     */
    public function scopeForEvent($query, int $eventId)
    {
        return $query->whereHas('ticketOrderItem.ticketOrder', fn ($q) => $q->where('event_id', $eventId));
    }

    public function ticketOrderItem(): BelongsTo
    {
        return $this->belongsTo(TicketOrderItem::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function claimedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkinEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'checkin_event_id');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function exhibitorLeads(): HasMany
    {
        return $this->hasMany(ExhibitorLead::class);
    }
}
