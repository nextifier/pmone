<?php

namespace App\Services\Pricing;

use App\Contracts\Pricing\Purchasable;
use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Models\AppliedAdjustment;
use App\Models\PromotionRule;
use Illuminate\Support\Collection;

/**
 * Central calculation engine for all purchasable entities.
 *
 * Calculation order:
 *  1. subtotal = sum of pricingLines amounts
 *  2. taxableBase = sum of pricingLines amounts where taxable
 *  3. apply penalties (additive, before tax)
 *  4. apply discounts (clamped to remaining base, before tax)
 *  5. netTaxable = taxableBase + penalties - discounts
 *  6. tax = netTaxable * taxRate
 *  7. service = netTaxable * serviceRate
 *  8. total = max(0, netTaxable + tax + service + nonTaxable)
 *
 * Discount value_type dispatch:
 *  - percentage / fixed_amount: classic value-based, applied to remaining taxable base
 *  - buy_x_get_y: cheapest-in-cart, discount = sum of qty_free units (per pass)
 *  - tiered_percentage / tiered_fixed_amount: pick highest matching tier by qty or amount
 *  - bundle_price: every N items priced at fixed bundle total (cheapest fills bundle)
 *  - free_addon: discount = sum of selected line_keys up to qty cap
 *
 * All money math uses PHP_ROUND_HALF_UP (Indonesia tax authority convention).
 */
class PricingService
{
    public function recalculate(Purchasable $entity): PricingResult
    {
        return $this->compute($entity, hypothetical: null);
    }

    /**
     * Compute pricing, persist totals to entity, and update each adjustment's
     * resolved amount + line_breakdown.
     */
    public function recalculateAndPersist(Purchasable $entity): PricingResult
    {
        $result = $this->recalculate($entity);

        foreach ($result->adjustments as $snapshot) {
            if (! empty($snapshot['id'])) {
                AppliedAdjustment::query()
                    ->whereKey($snapshot['id'])
                    ->update([
                        'amount' => $snapshot['amount'],
                        'base_amount' => $snapshot['base_amount'],
                        'line_breakdown' => $snapshot['line_breakdown'] ?? null,
                    ]);
            }
        }

        $entity->persistTotals($result->toEntityColumns());

        return $result;
    }

    public function preview(Purchasable $entity, ?PromotionRule $hypothetical = null): PricingResult
    {
        return $this->compute($entity, hypothetical: $hypothetical);
    }

    private function compute(Purchasable $entity, ?PromotionRule $hypothetical): PricingResult
    {
        $lines = $entity->pricingLines();
        $subtotal = $this->sumLines($lines);
        $taxableBase = $this->sumLines($lines, taxableOnly: true);
        $nonTaxable = $subtotal - $taxableBase;

        $items = method_exists($entity, 'purchaseItems') ? $entity->purchaseItems() : [];

        $existing = $entity->adjustments()
            ->whereNull('voided_at')
            ->orderBy('id')
            ->get();

        if ($hypothetical) {
            $transient = new AppliedAdjustment([
                'kind' => $hypothetical->kind?->value,
                'label' => $hypothetical->name,
                'value_type' => $hypothetical->value_type?->value,
                'value' => $hypothetical->value,
                'value_config' => $hypothetical->value_config,
                'base_amount' => 0,
                'amount' => 0,
                'promotion_rule_id' => $hypothetical->id,
            ]);
            $transient->setRelation('promotionRule', $hypothetical);
            $existing = $existing->push($transient);
        }

        $penaltyResolved = $this->resolvePenalties($existing, $taxableBase);
        $penaltyTotal = $penaltyResolved['total'];

        $discountResolved = $this->resolveDiscounts(
            $existing,
            taxableBase: $taxableBase,
            penaltyTotal: $penaltyTotal,
            subtotal: $subtotal,
            items: $items,
        );
        $discountTotal = $discountResolved['total'];

        $netTaxable = max(0.0, $taxableBase + $penaltyTotal - $discountTotal);

        $taxRate = $entity->taxRate();
        $serviceRate = $entity->serviceChargeRate();

        $taxAmount = $this->roundHalfUp($netTaxable * $taxRate);
        $serviceAmount = $this->roundHalfUp($netTaxable * $serviceRate);

        $total = max(0.0, $netTaxable + $taxAmount + $serviceAmount + $nonTaxable);

        return new PricingResult(
            subtotal: $this->roundHalfUp($subtotal),
            taxableBase: $this->roundHalfUp($taxableBase),
            penaltyAmount: $this->roundHalfUp($penaltyTotal),
            discountAmount: $this->roundHalfUp($discountTotal),
            taxAmount: $taxAmount,
            serviceChargeAmount: $serviceAmount,
            totalAmount: $this->roundHalfUp($total),
            lines: $lines,
            adjustments: array_merge($penaltyResolved['snapshots'], $discountResolved['snapshots']),
        );
    }

    /**
     * @param  array<int, array{key: string, amount: float, taxable: bool}>  $lines
     */
    private function sumLines(array $lines, bool $taxableOnly = false): float
    {
        $sum = 0.0;

        foreach ($lines as $line) {
            if ($taxableOnly && ! ($line['taxable'] ?? false)) {
                continue;
            }
            $sum += (float) ($line['amount'] ?? 0);
        }

        return $sum;
    }

    /**
     * @param  Collection<int, AppliedAdjustment>  $adjustments
     * @return array{total: float, snapshots: array<int, array<string, mixed>>}
     */
    private function resolvePenalties(Collection $adjustments, float $taxableBase): array
    {
        $penalties = $adjustments->filter(fn (AppliedAdjustment $a) => $a->kind === AdjustmentKind::Penalty);

        $total = 0.0;
        $snapshots = [];

        foreach ($penalties as $adj) {
            $base = $taxableBase + $total;
            $amount = $this->resolveSimpleAmount($adj->value_type, (float) $adj->value, $base);

            $total += $amount;

            $snapshots[] = $this->snapshot($adj, base: $base, amount: $amount, breakdown: null);
        }

        return ['total' => $total, 'snapshots' => $snapshots];
    }

    /**
     * @param  Collection<int, AppliedAdjustment>  $adjustments
     * @param  array<int, array<string, mixed>>  $items
     * @return array{total: float, snapshots: array<int, array<string, mixed>>}
     */
    private function resolveDiscounts(
        Collection $adjustments,
        float $taxableBase,
        float $penaltyTotal,
        float $subtotal,
        array $items,
    ): array {
        $discounts = $adjustments->filter(fn (AppliedAdjustment $a) => $a->kind === AdjustmentKind::Discount);

        $total = 0.0;
        $snapshots = [];

        $discounts = $discounts->sortBy(function (AppliedAdjustment $a) {
            return ($a->promotionRule?->priority ?? 100) * 1000000 + $a->id;
        });

        foreach ($discounts as $adj) {
            $rule = $adj->promotionRule;
            $appliesBeforeTax = $rule?->applies_before_tax ?? true;

            $base = $appliesBeforeTax
                ? ($taxableBase + $penaltyTotal - $total)
                : ($subtotal + $penaltyTotal - $total);
            $base = max(0.0, $base);

            $resolved = $this->computeDiscountAmount(
                adjustment: $adj,
                base: $base,
                items: $items,
            );
            $amount = $resolved['amount'];
            $breakdown = $resolved['breakdown'];

            $cap = $rule?->max_discount_amount;
            if ($cap !== null && $cap > 0) {
                $amount = min($amount, (float) $cap);
            }

            $remaining = max(0.0, $taxableBase + $penaltyTotal - $total);
            $amount = min($amount, $remaining);

            $total += $amount;

            $snapshots[] = $this->snapshot($adj, base: $base, amount: $amount, breakdown: $breakdown);
        }

        return ['total' => $total, 'snapshots' => $snapshots];
    }

    /**
     * Dispatch by value_type. Returns ['amount' => float, 'breakdown' => array|null].
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{amount: float, breakdown: array<string, mixed>|null}
     */
    private function computeDiscountAmount(AppliedAdjustment $adjustment, float $base, array $items): array
    {
        $type = $adjustment->value_type;
        $value = (float) $adjustment->value;
        $config = $this->resolveConfig($adjustment);
        $rule = $adjustment->promotionRule;
        $applicability = $rule?->applicability ?? [];

        return match ($type) {
            AdjustmentValueType::Percentage,
            AdjustmentValueType::FixedAmount => [
                'amount' => $this->resolveSimpleAmount($type, $value, $base),
                'breakdown' => null,
            ],
            AdjustmentValueType::BuyXGetY => $this->computeBuyXGetY($config, $items, $applicability),
            AdjustmentValueType::TieredPercentage,
            AdjustmentValueType::TieredFixedAmount => $this->computeTiered($type, $config, $items, $applicability, $base),
            AdjustmentValueType::BundlePrice => $this->computeBundlePrice($config, $items, $applicability),
            AdjustmentValueType::FreeAddon => $this->computeFreeAddon($config, $items, $applicability),
        };
    }

    private function resolveSimpleAmount(AdjustmentValueType $type, float $value, float $base): float
    {
        return match ($type) {
            AdjustmentValueType::FixedAmount => $this->roundHalfUp($value),
            AdjustmentValueType::Percentage => $this->roundHalfUp($base * $value / 100),
            default => 0.0,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $applicability
     * @return array{amount: float, breakdown: array<string, mixed>}
     */
    private function computeBuyXGetY(array $config, array $items, array $applicability): array
    {
        $buy = max(1, (int) ($config['buy_qty'] ?? 1));
        $free = max(1, (int) ($config['get_free_qty'] ?? 1));
        $eligible = $this->filterItemsForPromo($items, $applicability, $config);

        if (count($eligible) < $buy + $free) {
            return ['amount' => 0.0, 'breakdown' => ['eligible_units' => count($eligible), 'free_units' => []]];
        }

        usort($eligible, fn ($a, $b) => $a['unit_price'] <=> $b['unit_price']);

        $passes = intdiv(count($eligible), $buy + $free);
        $freeUnits = $passes * $free;

        $discount = 0.0;
        $picks = [];
        for ($i = 0; $i < $freeUnits; $i++) {
            $unit = $eligible[$i];
            $discount += (float) $unit['unit_price'];
            $picks[] = [
                'item_id' => $unit['item_id'] ?? null,
                'unit_price' => (float) $unit['unit_price'],
                'line_key' => $unit['line_key'] ?? null,
            ];
        }

        return [
            'amount' => $this->roundHalfUp($discount),
            'breakdown' => [
                'passes' => $passes,
                'buy_qty' => $buy,
                'get_free_qty' => $free,
                'free_units' => $picks,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $applicability
     * @return array{amount: float, breakdown: array<string, mixed>}
     */
    private function computeTiered(AdjustmentValueType $type, array $config, array $items, array $applicability, float $base): array
    {
        $tiers = $config['tiers'] ?? [];
        $metric = $config['metric'] ?? 'qty';
        $eligible = $this->filterItemsForPromo($items, $applicability, $config);

        $current = $metric === 'amount'
            ? array_sum(array_column($eligible, 'unit_price'))
            : count($eligible);

        $matched = null;
        foreach ($tiers as $tier) {
            $threshold = (float) ($tier['min'] ?? 0);
            if ($current >= $threshold && ($matched === null || $threshold >= (float) ($matched['min'] ?? 0))) {
                $matched = $tier;
            }
        }

        if (! $matched) {
            return ['amount' => 0.0, 'breakdown' => ['current' => $current, 'matched' => null]];
        }

        $tierValue = (float) ($matched['value'] ?? 0);

        $amount = $type === AdjustmentValueType::TieredPercentage
            ? $this->roundHalfUp($base * $tierValue / 100)
            : $this->roundHalfUp($tierValue);

        return [
            'amount' => $amount,
            'breakdown' => [
                'metric' => $metric,
                'current' => $current,
                'matched_min' => (float) ($matched['min'] ?? 0),
                'matched_value' => $tierValue,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $applicability
     * @return array{amount: float, breakdown: array<string, mixed>}
     */
    private function computeBundlePrice(array $config, array $items, array $applicability): array
    {
        $bundleQty = max(1, (int) ($config['bundle_qty'] ?? 1));
        $bundlePrice = max(0.0, (float) ($config['bundle_price'] ?? 0));
        $eligible = $this->filterItemsForPromo($items, $applicability, $config);

        if (count($eligible) < $bundleQty) {
            return ['amount' => 0.0, 'breakdown' => ['eligible_units' => count($eligible), 'bundles' => 0]];
        }

        // Sort cheapest first - bundle fills with cheapest units (most favorable to customer)
        usort($eligible, fn ($a, $b) => $a['unit_price'] <=> $b['unit_price']);

        $bundles = intdiv(count($eligible), $bundleQty);
        $totalUnitsInBundles = $bundles * $bundleQty;

        $originalSum = 0.0;
        $picks = [];
        for ($i = 0; $i < $totalUnitsInBundles; $i++) {
            $unit = $eligible[$i];
            $originalSum += (float) $unit['unit_price'];
            $picks[] = ['item_id' => $unit['item_id'] ?? null, 'unit_price' => (float) $unit['unit_price']];
        }

        $newSum = $bundles * $bundlePrice;
        $discount = max(0.0, $originalSum - $newSum);

        return [
            'amount' => $this->roundHalfUp($discount),
            'breakdown' => [
                'bundles' => $bundles,
                'bundle_qty' => $bundleQty,
                'bundle_price' => $bundlePrice,
                'original_sum' => $this->roundHalfUp($originalSum),
                'new_sum' => $this->roundHalfUp($newSum),
                'units_in_bundles' => $picks,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $applicability
     * @return array{amount: float, breakdown: array<string, mixed>}
     */
    private function computeFreeAddon(array $config, array $items, array $applicability): array
    {
        $maxQty = isset($config['max_free_qty']) ? max(1, (int) $config['max_free_qty']) : null;
        $eligible = $this->filterItemsForPromo($items, $applicability, $config);

        if (empty($eligible)) {
            return ['amount' => 0.0, 'breakdown' => ['free_units' => []]];
        }

        // Cheapest first so free-addon doesn't accidentally consume the priciest line.
        usort($eligible, fn ($a, $b) => $a['unit_price'] <=> $b['unit_price']);

        $picks = [];
        $discount = 0.0;
        foreach ($eligible as $unit) {
            if ($maxQty !== null && count($picks) >= $maxQty) {
                break;
            }
            $discount += (float) $unit['unit_price'];
            $picks[] = [
                'item_id' => $unit['item_id'] ?? null,
                'unit_price' => (float) $unit['unit_price'],
                'line_key' => $unit['line_key'] ?? null,
            ];
        }

        return [
            'amount' => $this->roundHalfUp($discount),
            'breakdown' => [
                'free_units' => $picks,
                'max_free_qty' => $maxQty,
            ],
        ];
    }

    /**
     * Filter purchase items by applicability + value_config target keys.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $applicability
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    private function filterItemsForPromo(array $items, array $applicability, array $config): array
    {
        $targetLineKeys = $config['target_line_keys'] ?? $applicability['applies_to_categories'] ?? null;
        $targetRoomTypes = $applicability['room_types'] ?? null;
        $targetEventProducts = $applicability['event_products'] ?? null;
        $targetTicketTypes = $applicability['ticket_types'] ?? null;
        $targetTransferOptions = $config['transfer_option_ids'] ?? null;

        return array_values(array_filter($items, function ($it) use ($targetLineKeys, $targetRoomTypes, $targetEventProducts, $targetTicketTypes, $targetTransferOptions) {
            if (! empty($targetLineKeys) && ! in_array($it['line_key'] ?? null, (array) $targetLineKeys, true)) {
                return false;
            }
            $meta = $it['meta'] ?? [];
            if (! empty($targetRoomTypes) && ($it['item_type'] ?? null) === 'room_type') {
                if (! in_array((int) ($meta['room_type_id'] ?? 0), array_map('intval', (array) $targetRoomTypes), true)) {
                    return false;
                }
            }
            if (! empty($targetEventProducts) && ($it['item_type'] ?? null) === 'event_product') {
                if (! in_array((int) ($meta['event_product_id'] ?? 0), array_map('intval', (array) $targetEventProducts), true)) {
                    return false;
                }
            }
            if (! empty($targetTicketTypes) && ($it['item_type'] ?? null) === 'event_product') {
                if (! in_array((int) ($it['category_id'] ?? 0), array_map('intval', (array) $targetTicketTypes), true)) {
                    return false;
                }
            }
            if (! empty($targetTransferOptions) && ($it['item_type'] ?? null) === 'transfer_option') {
                if (! in_array((int) ($meta['transfer_option_id'] ?? 0), array_map('intval', (array) $targetTransferOptions), true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Resolve value_config from adjustment (live or transient).
     *
     * @return array<string, mixed>
     */
    private function resolveConfig(AppliedAdjustment $adjustment): array
    {
        $config = $adjustment->value_config;

        if (is_array($config)) {
            return $config;
        }

        $rule = $adjustment->promotionRule;
        if ($rule && is_array($rule->value_config)) {
            return $rule->value_config;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $breakdown
     * @return array<string, mixed>
     */
    private function snapshot(AppliedAdjustment $adj, float $base, float $amount, ?array $breakdown): array
    {
        return [
            'id' => $adj->id,
            'kind' => $adj->kind?->value,
            'label' => $adj->label,
            'value_type' => $adj->value_type?->value,
            'value' => (float) $adj->value,
            'base_amount' => $this->roundHalfUp($base),
            'amount' => $this->roundHalfUp($amount),
            'line_breakdown' => $breakdown,
            'promotion_rule_id' => $adj->promotion_rule_id,
            'promo_code_id' => $adj->promo_code_id,
        ];
    }

    private function roundHalfUp(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }
}
