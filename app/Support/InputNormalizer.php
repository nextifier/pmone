<?php

namespace App\Support;

use App\Helpers\PhoneCountryHelper;

final class InputNormalizer
{
    /**
     * Normalize an email address: trim + lowercase. Empty becomes null.
     */
    public static function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Normalize a person's name to Title Case, but only when the input is
     * entirely uppercase or entirely lowercase. Mixed-case input is treated
     * as intentional (e.g. "McDonald", "van der Berg") and preserved.
     */
    public static function personName(?string $value): ?string
    {
        $value = self::collapseWhitespace($value);

        if ($value === null) {
            return null;
        }

        $letters = preg_replace('/[^\p{L}]+/u', '', $value);

        if ($letters === '' || $letters === null) {
            return $value;
        }

        $isAllUpper = $letters === mb_strtoupper($letters, 'UTF-8');
        $isAllLower = $letters === mb_strtolower($letters, 'UTF-8');

        if (! $isAllUpper && ! $isAllLower) {
            return $value;
        }

        return (string) preg_replace_callback(
            "/(^|[\s\-'’.])(\p{L})/u",
            fn (array $match): string => $match[1].mb_strtoupper($match[2], 'UTF-8'),
            mb_strtolower($value, 'UTF-8'),
        );
    }

    /**
     * Normalize an organization/company/place name: trim and collapse
     * whitespace only. Casing is never altered.
     */
    public static function orgName(?string $value): ?string
    {
        return self::collapseWhitespace($value);
    }

    /**
     * Normalize a booth number so a hall/block prefix is always separated from
     * its number by a dash: "8A81" becomes "8A-81" and "B01" becomes "B-01".
     *
     * The dash goes in front of a token's FINAL digit group only, which is what
     * keeps compound prefixes intact - "B1A01" becomes "B1A-01", not
     * "B-1A-01". Values that are already correct ("6E-01", "A-04A", "AA-105")
     * are left alone, and the rule is idempotent.
     *
     * Letters are uppercased: booth numbers are signage, and case is never
     * meaningful in one. It also keeps `boothPrimaryId()` honest, since that
     * groups shared booths by an exact string match - "B1B35" and "B1b35" would
     * otherwise count as two different physical booths.
     *
     * Tokens are separated by comma, slash, ampersand, or whitespace, and every
     * separator survives as typed; only spacing around commas is tidied. The
     * mirror of this rule for live typing lives in
     * frontend/app/utils/boothNumber.js - change both together.
     */
    public static function boothNumber(?string $value): ?string
    {
        $value = self::collapseWhitespace($value);

        if ($value === null) {
            return null;
        }

        $value = mb_strtoupper((string) preg_replace('/\s*,\s*/u', ', ', $value), 'UTF-8');

        return (string) preg_replace('/([A-Z])(\d+)(?![^\s,\/&]*\d)/u', '$1-$2', $value);
    }

    /**
     * Normalize a phone number to international format. Empty becomes null.
     */
    public static function phone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return PhoneCountryHelper::normalizePhoneNumber($value);
    }

    /**
     * Normalize a list of email addresses, dropping empty entries.
     *
     * @param  array<int, mixed>|null  $values
     * @return array<int, string>
     */
    public static function emailList(?array $values): array
    {
        return self::normalizeList($values, [self::class, 'email']);
    }

    /**
     * Normalize a list of phone numbers, dropping empty entries.
     *
     * @param  array<int, mixed>|null  $values
     * @return array<int, string>
     */
    public static function phoneList(?array $values): array
    {
        return self::normalizeList($values, [self::class, 'phone']);
    }

    /**
     * Normalize a list of {label, number} phone entries (projects.phone
     * shape), rewriting each number to international format while leaving
     * labels and unknown shapes untouched.
     *
     * @param  array<int, mixed>|null  $values
     * @return array<int, mixed>
     */
    public static function labeledPhoneList(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        return array_values(array_map(function ($entry) {
            if (is_array($entry) && isset($entry['number']) && is_string($entry['number'])) {
                $entry['number'] = self::phone($entry['number']) ?? $entry['number'];
            }

            return $entry;
        }, $values));
    }

    /**
     * @param  array<int, mixed>|null  $values
     * @param  callable(?string): ?string  $normalizer
     * @return array<int, string>
     */
    private static function normalizeList(?array $values, callable $normalizer): array
    {
        if ($values === null) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = $normalizer($value);

            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private static function collapseWhitespace(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }
}
