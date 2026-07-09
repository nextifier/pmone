<?php

namespace App\Models;

use App\Enums\EmailEventType;
use Database\Factories\EmailMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One outgoing email, recorded the moment SES accepts it. SES itself keeps no
 * searchable history of what you sent, so this table is the only place a sent
 * message can be looked up later.
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
        'configuration_set',
        'status',
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
