<?php

namespace App\Models;

use App\Enums\EmailEventType;
use Database\Factories\EmailEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single delivery event for a message. Kept even when the owning EmailMessage
 * is unknown, so a bounce is never silently dropped.
 *
 * @property int $id
 * @property string $message_id
 * @property EmailEventType $type
 * @property string $recipient
 * @property string|null $subtype
 * @property string|null $diagnostic
 * @property Carbon $occurred_at
 * @property array<array-key, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EmailMessage|null $message
 *
 * @method static \Database\Factories\EmailEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereDiagnostic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereRecipient($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereSubtype($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
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
