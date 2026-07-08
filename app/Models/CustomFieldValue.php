<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One stored answer for a CustomField, keyed by a polymorphic subject: User
 * for business-matching intake, Attendee for ticket-registration questions.
 * Scalar answers are wrapped as `[value]` (see FormFieldTypes::normalizeStored)
 * to keep the historical FieldResponse storage convention.
 *
 * @property int $id
 * @property int $custom_field_id
 * @property string $subject_type
 * @property int $subject_id
 * @property array<array-key, mixed>|null $value
 *
 * @mixin \Eloquent
 */
class CustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_field_id',
        'subject_type',
        'subject_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
