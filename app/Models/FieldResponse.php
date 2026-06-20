<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's answer to an event business-matching custom field. Stored per User
 * (not per attendee/buyer): matching is about the person who attends and meets
 * exhibitors, so a single field per (user, custom_field) is the source of truth.
 *
 * @property int $id
 * @property int $user_id
 * @property int $event_custom_field_id
 * @property array<array-key, mixed>|null $value
 * @property-read User|null $user
 * @property-read EventCustomField|null $eventCustomField
 *
 * @mixin \Eloquent
 */
class FieldResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_custom_field_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventCustomField(): BelongsTo
    {
        return $this->belongsTo(EventCustomField::class);
    }
}
