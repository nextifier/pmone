<?php

namespace App\Support;

use App\Models\Event;

/**
 * Thin adapter over CustomFieldValidation for the public business-matching
 * payload shape ({custom_field_id, value} rows keyed by numeric id). Posted
 * ids resolve by id OR legacy_id: event-website checkouts opened before the
 * custom-fields migration deploy still post the old event_custom_fields ids
 * for one release.
 */
class BusinessMatchingValidation
{
    /**
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

        $fields = $event->eventCustomFields()->where('is_active', true)->get();

        $values = [];
        foreach ($responses as $resp) {
            $id = (int) ($resp['custom_field_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $field = $fields->first(fn ($f) => $f->id === $id || $f->legacy_id === $id);

            if ($field !== null) {
                $values[(string) $field->id] = $resp['value'] ?? null;
            }
        }

        return CustomFieldValidation::errorsFor($fields, $values, $keyPrefix, 'id');
    }
}
