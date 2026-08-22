<?php

namespace App\Support;

/**
 * Server-side lookup for the dependent province/city fields.
 *
 * Reads the same two datasets the admin already ships to the browser, so there
 * is one list of regions rather than a second copy that drifts. Codes are BPS:
 * two digits for a province, four for a kabupaten/kota whose first two digits
 * are its province.
 *
 * Values are stored as LABELS, not codes - LocationCombobox has always stored
 * the label, and contacts, hotels and brands already hold addresses in that
 * shape. Comparisons here are therefore label-based and case-insensitive.
 */
class IndonesiaRegions
{
    private const PROVINCES_PATH = 'frontend/app/data/indonesia-provinces.json';

    private const CITIES_PATH = 'frontend/app/data/indonesia-cities.json';

    public const COUNTRY_LABEL = 'Indonesia';

    /** @var array<int, array{value: string, label: string}>|null */
    private static ?array $provinces = null;

    /** @var array<int, array{value: string, label: string, province: string}>|null */
    private static ?array $cities = null;

    public static function isIndonesia(mixed $country): bool
    {
        return is_string($country)
            && mb_strtolower(trim($country)) === mb_strtolower(self::COUNTRY_LABEL);
    }

    public static function hasProvince(string $label): bool
    {
        return self::provinceCodeFor($label) !== null;
    }

    public static function cityBelongsToProvince(string $cityLabel, string $provinceLabel): bool
    {
        $code = self::provinceCodeFor($provinceLabel);

        if ($code === null) {
            return false;
        }

        foreach (self::cities() as $city) {
            if ($city['province'] === $code && self::matches($city['label'], $cityLabel)) {
                return true;
            }
        }

        return false;
    }

    public static function provinceCodeFor(string $label): ?string
    {
        foreach (self::provinces() as $province) {
            if (self::matches($province['label'], $label)) {
                return $province['value'];
            }
        }

        return null;
    }

    private static function matches(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    /** @return array<int, array{value: string, label: string}> */
    private static function provinces(): array
    {
        return self::$provinces ??= self::read(self::PROVINCES_PATH);
    }

    /** @return array<int, array{value: string, label: string, province: string}> */
    private static function cities(): array
    {
        return self::$cities ??= self::read(self::CITIES_PATH);
    }

    /** @return array<int, array<string, string>> */
    private static function read(string $relativePath): array
    {
        $path = base_path($relativePath);

        if (! is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
