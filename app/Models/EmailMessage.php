<?php

namespace App\Models;

use App\Enums\EmailEventType;
use Database\Factories\EmailMessageFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One outgoing email, recorded the moment Resend accepts it. This table is the
 * searchable history of what was sent, backfilled from the Resend API and kept
 * in step by the delivery webhook.
 *
 * @property int $id
 * @property string $message_id
 * @property string $mailer
 * @property string $from_address
 * @property string|null $subject
 * @property array<array-key, mixed> $recipients
 * @property EmailEventType $status
 * @property int $status_rank
 * @property Carbon $sent_at
 * @property Carbon|null $last_event_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $bounce_type
 * @property-read Collection<int, EmailEvent> $events
 * @property-read int|null $events_count
 *
 * @method static \Database\Factories\EmailMessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereBounceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereFromAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereLastEventAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereMailer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereRecipients($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereStatusRank($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailMessage whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class EmailMessage extends Model
{
    /** @use HasFactory<EmailMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'message_id',
        'mailer',
        'from_address',
        'subject',
        'recipients',
        'status',
        'bounce_type',
        'status_rank',
        'sent_at',
        'last_event_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'status' => EmailEventType::class,
            'sent_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<EmailEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(EmailEvent::class, 'message_id', 'message_id');
    }

    /**
     * Status only ever moves towards a more final outcome, so a delivery
     * notification arriving late cannot erase a bounce that already landed.
     */
    public function applyEvent(EmailEventType $type, \DateTimeInterface $occurredAt): void
    {
        $changed = false;

        if ($type->rank() > $this->status_rank) {
            $this->status = $type;
            $this->status_rank = $type->rank();
            $changed = true;
        }

        if ($this->last_event_at === null || $occurredAt > $this->last_event_at) {
            $this->last_event_at = $occurredAt;
            $changed = true;
        }

        if ($changed) {
            $this->save();
        }
    }
}
