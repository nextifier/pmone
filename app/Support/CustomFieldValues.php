<?php

namespace App\Support;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CustomFieldValues
{
    /**
     * Merge incoming answers into an existing ulid-keyed value map, keeping
     * only known field ulids and dropping layout-only sections. The exact
     * analogue of PublicFormController's response whitelisting, reused by
     * document submissions and any other JSON-blob answer store.
     *
     * @param  array<string, mixed>|null  $current
     * @param  Collection<int, CustomField>  $fields
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function mergeUlidKeyed(?array $current, Collection $fields, array $incoming): array
    {
        $merged = $current ?? [];

        $knownUlids = $fields
            ->reject(fn (CustomField $field) => $field->type === CustomField::TYPE_SECTION)
            ->pluck('ulid')
            ->all();

        foreach ($knownUlids as $ulid) {
            if (array_key_exists($ulid, $incoming)) {
                $merged[$ulid] = $incoming[$ulid];
            }
        }

        return $merged;
    }

    /**
     * Upsert row-per-answer values for a subject (User for business matching,
     * Attendee for ticket registration). `$values` is keyed per `$keyBy`;
     * unknown keys are dropped. Scalars are wrapped as `[value]` to keep the
     * historical FieldResponse storage convention consumed by
     * FormFieldTypes::normalizeStored.
     *
     * @param  Collection<int, CustomField>  $fields
     * @param  array<string, mixed>  $values
     */
    public static function store(Model $subject, Collection $fields, array $values, string $keyBy = 'ulid'): void
    {
        foreach ($fields as $field) {
            $fieldKey = (string) $field->{$keyBy};

            if (! array_key_exists($fieldKey, $values)) {
                continue;
            }

            $value = self::normalizeByType($field, $values[$fieldKey]);

            CustomFieldValue::query()->updateOrCreate(
                [
                    'custom_field_id' => $field->id,
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                ],
                ['value' => is_array($value) ? $value : [$value]],
            );
        }
    }

    /**
     * Normalize an answer according to its field type (email lowercased,
     * phone in international format). Other types pass through untouched.
     * Handles both scalars and the array/multi-value shapes.
     */
    public static function normalizeByType(CustomField $field, mixed $value): mixed
    {
        $normalizer = match ($field->type) {
            CustomField::TYPE_EMAIL => 'email',
            CustomField::TYPE_PHONE => 'phone',
            default => null,
        };

        if ($normalizer === null) {
            return $value;
        }

        if (is_string($value)) {
            return InputNormalizer::{$normalizer}($value);
        }

        if (is_array($value)) {
            return array_map(
                fn ($item) => is_string($item) ? InputNormalizer::{$normalizer}($item) : $item,
                $value,
            );
        }

        return $value;
    }

    /**
     * Stored answers for a subject as a {ulid: logical value} map, unwrapping
     * the `[value]` scalar convention per field type.
     *
     * @param  Collection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    public static function answersByUlid(Model $subject, Collection $fields): array
    {
        if ($fields->isEmpty()) {
            return [];
        }

        $stored = CustomFieldValue::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereIn('custom_field_id', $fields->pluck('id'))
            ->pluck('value', 'custom_field_id');

        $answers = [];

        foreach ($fields as $field) {
            if (! $stored->has($field->id)) {
                continue;
            }

            $answers[$field->ulid] = FormFieldTypes::normalizeStored($field->type, $stored->get($field->id));
        }

        return $answers;
    }

    /**
     * Format a stored (wrapped) value for export or display.
     */
    public static function format(CustomField $field, mixed $stored): string
    {
        return FormFieldTypes::formatStoredValue($field->type, $stored, $field->options ?? []);
    }
}
