<?php

namespace App\Support;

use App\Models\CustomField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class CustomFieldValidation
{
    /**
     * Validate answers against a set of CustomField definitions, reusing the
     * shared FormFieldTypes rules. `$values` is keyed per `$keyBy` (ulid, id,
     * or key); returns a map of "{keyPrefix}.{fieldKey}" => first error message
     * (empty when everything is valid), so any caller (public form, checkout
     * registration, brand profile, document mini-form) surfaces identical
     * errors regardless of its own payload shape.
     *
     * @param  Collection<int, CustomField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public static function errorsFor(
        Collection $fields,
        array $values,
        string $keyPrefix = 'responses',
        string $keyBy = 'ulid',
    ): array {
        $errors = [];

        foreach ($fields as $field) {
            if ($field->type === CustomField::TYPE_SECTION) {
                continue;
            }

            $fieldKey = (string) $field->{$keyBy};
            $value = $values[$fieldKey] ?? null;

            // Treat blank strings as absent so `required` fails and `nullable`
            // short-circuits, matching how public forms drop empty answers.
            if ($value === '') {
                $value = null;
            }

            $rules = FormFieldTypes::rulesFor($field, 'value');

            $validator = Validator::make(
                ['value' => $value],
                $rules,
                [],
                ['value' => $field->label],
            );

            if ($validator->fails()) {
                $errors[$keyPrefix.'.'.$fieldKey] = $validator->errors()->first();
            }
        }

        return $errors;
    }
}
