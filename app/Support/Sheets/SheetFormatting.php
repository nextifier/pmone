<?php

namespace App\Support\Sheets;

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
     * One formatted cell per custom-field column, in the given order. Each column
     * may map to several storage keys (fields sharing a label across projects);
     * the value is the first non-empty one among them.
     *
     * @param  Collection<int, array{label: string, type: string, options: array, keys: array<int, string>}>  $columns
     * @param  array<string, mixed>|null  $values
     * @return array<int, string>
     */
    public static function customFieldColumns(Collection $columns, ?array $values): array
    {
        $values ??= [];

        return $columns
            ->map(function (array $column) use ($values) {
                $value = null;

                foreach ($column['keys'] as $key) {
                    $candidate = $values[$key] ?? null;
                    if ($candidate !== null && $candidate !== '' && $candidate !== []) {
                        $value = $candidate;
                        break;
                    }
                }

                return FormFieldTypes::formatValueForType(
                    $column['type'],
                    $value,
                    $column['options'] ?? [],
                );
            })
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
