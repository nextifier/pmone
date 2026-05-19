<?php

namespace App\Services\Promotion;

use App\Contracts\Pricing\Purchasable;
use App\DTOs\Promotion\ApplicabilityResult;
use App\Models\PromoCodeUsage;

/**
 * Validates declarative applicability filter against a purchasable entity.
 *
 * Supported keys (all optional, missing = no constraint):
 *  - events:                array<int>      (event_ids)
 *  - hotels:                array<int>      (hotel_ids)
 *  - room_types:            array<int>
 *  - event_products:        array<int>
 *  - ticket_types:          array<int>
 *  - brands:                array<int>
 *  - min_nights:            int
 *  - min_qty:               int
 *  - first_purchase_only:   bool
 *  - guest_email_domains:   array<string>   (lowercased, e.g. "@askindo.com")
 *  - weekdays:              array<int>      (ISO 1..7)
 *  - include_transfers:     bool
 *  - include_surcharge:     bool
 *  - applies_to_categories: array<string>   (pricingLines keys: "rooms"|"transfer"|"surcharge"|"subtotal" - used by PricingService to filter eligible items)
 *
 * Whitelist enforced - unknown keys cause silent skip (validated at write time).
 */
class ApplicabilityChecker
{
    private const KNOWN_KEYS = [
        'events',
        'hotels',
        'room_types',
        'event_products',
        'ticket_types',
        'brands',
        'min_nights',
        'min_qty',
        'first_purchase_only',
        'guest_email_domains',
        'weekdays',
        'include_transfers',
        'include_surcharge',
        'applies_to_categories',
    ];

    /**
     * @param  array<string, mixed>  $applicability
     */
    public function run(
        array $applicability,
        Purchasable $entity,
        string $email,
        ?int $userId = null,
    ): ApplicabilityResult {
        if (empty($applicability)) {
            return ApplicabilityResult::pass();
        }

        $context = $entity->getPurchaseContext();

        foreach ($applicability as $key => $value) {
            if (! in_array($key, self::KNOWN_KEYS, true)) {
                continue;
            }

            $result = $this->checkKey($key, $value, $context, $email, $userId);

            if (! $result->passes) {
                return $result;
            }
        }

        return ApplicabilityResult::pass();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function checkKey(string $key, mixed $value, array $context, string $email, ?int $userId): ApplicabilityResult
    {
        return match ($key) {
            'events' => $this->checkIdsIntersection($value, $context['event_id'] ?? null, 'event'),
            'hotels' => $this->checkIdsIntersection($value, $context['hotel_id'] ?? null, 'hotel'),
            'room_types' => $this->checkIdsArrayIntersection($value, $context['room_type_ids'] ?? [], 'room_type'),
            'event_products' => $this->checkIdsArrayIntersection($value, $context['event_product_ids'] ?? [], 'event_product'),
            'ticket_types' => $this->checkIdsArrayIntersection($value, $context['ticket_type_ids'] ?? [], 'ticket_type'),
            'brands' => $this->checkIdsIntersection($value, $context['brand_id'] ?? null, 'brand'),
            'min_nights' => $this->checkMin($value, $context['nights'] ?? 0, 'nights'),
            'min_qty' => $this->checkMin($value, $context['qty'] ?? 0, 'quantity'),
            'first_purchase_only' => $this->checkFirstPurchase($value, $email, $userId, $context['morph_class'] ?? ''),
            'guest_email_domains' => $this->checkEmailDomain($value, $email),
            'weekdays' => $this->checkWeekday($value),
            'include_transfers', 'include_surcharge', 'applies_to_categories' => ApplicabilityResult::pass(),
            default => ApplicabilityResult::pass(),
        };
    }

    private function checkIdsIntersection(mixed $expected, mixed $actual, string $label): ApplicabilityResult
    {
        if (! is_array($expected) || empty($expected)) {
            return ApplicabilityResult::pass();
        }

        if ($actual === null || ! in_array((int) $actual, array_map('intval', $expected), true)) {
            return ApplicabilityResult::fail("not_applicable_to_{$label}");
        }

        return ApplicabilityResult::pass();
    }

    /**
     * @param  array<int, int>  $actualIds
     */
    private function checkIdsArrayIntersection(mixed $expected, array $actualIds, string $label): ApplicabilityResult
    {
        if (! is_array($expected) || empty($expected)) {
            return ApplicabilityResult::pass();
        }

        $intersect = array_intersect(array_map('intval', $expected), array_map('intval', $actualIds));

        if (empty($intersect)) {
            return ApplicabilityResult::fail("not_applicable_to_{$label}");
        }

        return ApplicabilityResult::pass();
    }

    private function checkMin(mixed $threshold, mixed $actual, string $label): ApplicabilityResult
    {
        if ($threshold === null || (int) $threshold <= 0) {
            return ApplicabilityResult::pass();
        }

        if ((int) $actual < (int) $threshold) {
            return ApplicabilityResult::fail("minimum_{$label}_not_met");
        }

        return ApplicabilityResult::pass();
    }

    private function checkFirstPurchase(mixed $required, string $email, ?int $userId, string $morphClass): ApplicabilityResult
    {
        if (! $required) {
            return ApplicabilityResult::pass();
        }

        // Look for any prior non-voided usage by this email (best-effort heuristic)
        $hasPrior = PromoCodeUsage::query()
            ->whereNull('voided_at')
            ->where('email', strtolower(trim($email)))
            ->exists();

        if ($hasPrior) {
            return ApplicabilityResult::fail('not_first_purchase');
        }

        return ApplicabilityResult::pass();
    }

    private function checkEmailDomain(mixed $domains, string $email): ApplicabilityResult
    {
        if (! is_array($domains) || empty($domains)) {
            return ApplicabilityResult::pass();
        }

        $emailLower = strtolower(trim($email));

        foreach ($domains as $domain) {
            $needle = strtolower((string) $domain);
            if (! str_starts_with($needle, '@')) {
                $needle = '@'.$needle;
            }
            if (str_ends_with($emailLower, $needle)) {
                return ApplicabilityResult::pass();
            }
        }

        return ApplicabilityResult::fail('email_domain_not_allowed');
    }

    private function checkWeekday(mixed $weekdays): ApplicabilityResult
    {
        if (! is_array($weekdays) || empty($weekdays)) {
            return ApplicabilityResult::pass();
        }

        $today = (int) now()->isoWeekday();

        if (! in_array($today, array_map('intval', $weekdays), true)) {
            return ApplicabilityResult::fail('weekday_not_allowed');
        }

        return ApplicabilityResult::pass();
    }

    /**
     * Validate that all keys in $applicability are known. Used at write time.
     *
     * @param  array<string, mixed>  $applicability
     * @return array<int, string> list of unknown keys (empty = valid)
     */
    public static function unknownKeys(array $applicability): array
    {
        return array_values(array_diff(array_keys($applicability), self::KNOWN_KEYS));
    }
}
