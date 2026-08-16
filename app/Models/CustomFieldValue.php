<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One stored answer for a CustomField, keyed by a polymorphic subject: User
 * for business-matching intake, Attendee for ticket-registration questions.
 *
 * Scalar answers are wrapped as `[value]` (see FormFieldTypes::normalizeStored)
 * to keep the historical FieldResponse storage convention.
 *
 * @property int $id
 * @property int $custom_field_id
 * @property string $subject_type
 * @property int $subject_id
 * @property array<array-key, mixed>|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomField|null $customField
 * @property-read Model|\Eloquent $subject
 *
 * @method static \Database\Factories\CustomFieldValueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereCustomFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereSubjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomFieldValue whereValue($value)
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
