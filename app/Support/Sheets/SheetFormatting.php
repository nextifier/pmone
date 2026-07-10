<?php

namespace App\Support\Sheets;

use App\Models\CustomField;
use App\Support\FormFieldTypes;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SheetFormatting
{
    /**
     * Split an address jsonb into its four fixed columns.
     *
     * @return array{0: string, 1: string, 2: string, 3: string} [country, province, city, street]
     */
    public static function address(?array $address): array
    {
        return [
            $address['country'] ?? '-',
            $address['province'] ?? '-',
            $address['city'] ?? '-',
            $address['street'] ?? '-',
        ];
    }

    /**
     * One formatted cell per typed custom-field definition, in the given order.
     * Values are keyed by the field `key` (how Brand/BrandEvent store them).
     *
     * @param  Collection<int, CustomField>  $fields
     * @param  array<string, mixed>|null  $values
     * @return array<int, string>
     */
    public static function customFieldColumns(Collection $fields, ?array $values): array
    {
        $values ??= [];

        return $fields
            ->map(fn (CustomField $field) => FormFieldTypes::formatValueForType(
                $field->type,
                $values[$field->key] ?? null,
                $field->options ?? [],
            ))
            ->values()
            ->all();
    }

    /**
     * One cell per key for untyped jsonb values (no field catalog available).
     * Arrays are joined, booleans become Yes/No, scalars pass through.
     *
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>|null  $values
     * @return array<int, string>
     */
    public static function freeJsonColumns(array $keys, ?array $values): array
    {
        $values ??= [];

        return array_map(function (string $key) use ($values) {
            $value = $values[$key] ?? null;

            if ($value === null || $value === '' || $value === []) {
                return '-';
            }

            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_array($value)) {
                return collect($value)
                    ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))
                    ->implode(', ');
            }

            return (string) $value;
        }, $keys);
    }

    /**
     * Human header for an untyped jsonb key.
     */
    public static function headline(string $key): string
    {
        return Str::headline($key);
    }

    public static function dateTime(?CarbonInterface $dt): string
    {
        return $dt?->format('Y-m-d H:i:s') ?? '-';
    }
}
