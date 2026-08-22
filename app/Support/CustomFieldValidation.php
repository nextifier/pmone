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

                continue;
            }

            $dependencyError = self::dependencyErrorFor($field, $fields, $values, $keyBy);

            if ($dependencyError !== null) {
                $errors[$keyPrefix.'.'.$fieldKey] = $dependencyError;
            }
        }

        return $errors;
    }

    /**
     * Cross-field check for dependent location selects.
     *
     * `FormFieldTypes::rulesForType()` sees one field's type and its own options,
     * so it cannot express "this city belongs to that province". Only this method
     * has both the field collection and every answer, so the parent-child rule
     * lives here. The client narrows the dropdown; this is what makes the rule
     * true for a hand-crafted request.
     *
     * @param  Collection<int, CustomField>  $fields
     * @param  array<string, mixed>  $values
     */
    private static function dependencyErrorFor(
        CustomField $field,
        Collection $fields,
        array $values,
        string $keyBy,
    ): ?string {
        if (! in_array($field->type, [CustomField::TYPE_PROVINCE, CustomField::TYPE_CITY], true)) {
            return null;
        }

        $value = $values[(string) $field->{$keyBy}] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        $parentKey = $field->settings['depends_on'] ?? null;
        $parent = $parentKey
            ? $fields->firstWhere('system_key', $parentKey)
            : null;

        $parentValue = $parent
            ? ($values[(string) $parent->{$keyBy}] ?? null)
            : null;

        // The dataset covers Indonesia only. Outside it the field is a free-text
        // input by design, so there is nothing to check against.
        if ($field->type === CustomField::TYPE_PROVINCE) {
            if (! IndonesiaRegions::isIndonesia($parentValue)) {
                return null;
            }

            return IndonesiaRegions::hasProvince((string) $value)
                ? null
                : 'The selected province is not recognised.';
        }

        // City: the parent is the province, which itself only carries options
        // when the country is Indonesia.
        if ($parentValue === null || $parentValue === '') {
            return null;
        }

        if (! IndonesiaRegions::hasProvince((string) $parentValue)) {
            return null;
        }

        return IndonesiaRegions::cityBelongsToProvince((string) $value, (string) $parentValue)
            ? null
            : 'The selected city does not belong to the chosen province.';
    }
}
