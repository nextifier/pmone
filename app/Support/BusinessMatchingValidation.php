<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Validator;

class BusinessMatchingValidation
{
    /**
     * Validate business-matching intake answers against each active custom
     * field's type + required flag, reusing the shared Form Builder rules.
     * Returns a map of error path => message (empty when everything is valid),
     * so any caller (public ticket order, attendee dashboard) can surface the
     * same errors regardless of its own payload shape.
     *
     * @param  array<int, array<string, mixed>>  $responses  List of {custom_field_id, value}.
     * @return array<string, string>
     */
    public static function errorsFor(
        Event $event,
        bool $optIn,
        array $responses,
        string $keyPrefix = 'business_matching.responses',
    ): array {
        // No program, or the buyer opted out: there is nothing to answer.
        if (! $event->business_matching_enabled || ! $optIn) {
            return [];
        }

        $valuesById = [];
        foreach ($responses as $resp) {
            $id = (int) ($resp['custom_field_id'] ?? 0);
            if ($id > 0) {
                $valuesById[$id] = $resp['value'] ?? null;
            }
        }

        $errors = [];

        $fields = $event->eventCustomFields()->where('is_active', true)->get();

        foreach ($fields as $field) {
            $value = $valuesById[$field->id] ?? null;
            // Treat blank strings as absent so `required` fails and `nullable`
            // short-circuits, matching how the checkout drops empty answers.
            if ($value === '') {
                $value = null;
            }

            $optionValues = array_map('strval', (array) ($field->options ?? []));

            $rules = FormFieldTypes::rulesForType(
                $field->type,
                'value',
                (bool) $field->required,
                $optionValues,
            );

            $validator = Validator::make(
                ['value' => $value],
                $rules,
                [],
                ['value' => $field->label],
            );

            if ($validator->fails()) {
                $errors[$keyPrefix.'.'.$field->id] = $validator->errors()->first();
            }
        }

        return $errors;
    }
}
