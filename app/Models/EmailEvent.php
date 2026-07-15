<?php

namespace App\Models;

use App\Enums\EmailEventType;
use Database\Factories\EmailEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single delivery event for a message. Kept even when the owning EmailMessage
 * is unknown, so a bounce is never silently dropped.
 */
class EmailEvent extends Model
{
    /** @use HasFactory<EmailEventFactory> */
    use HasFactory;

    protected $fillable = [
        'message_id',
        'type',
        'recipient',
        'subtype',
        'diagnostic',
        'occurred_at',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmailEventType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EmailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'message_id', 'message_id');
    }
}
