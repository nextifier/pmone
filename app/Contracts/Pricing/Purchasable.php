<?php

namespace App\Contracts\Pricing;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for any entity that can be priced and have adjustments (discount/penalty) applied.
 */
interface Purchasable
{
    public function getKey();

    public function getMorphClass();

    /**
     * Return pricing lines as array of ['key' => string, 'amount' => float, 'taxable' => bool].
     *
     * @return array<int, array{key: string, amount: float, taxable: bool}>
     */
    public function pricingLines(): array;

    public function taxRate(): float;

    public function serviceChargeRate(): float;

    public function adjustments(): MorphMany;

    public function subtotalForDiscountBase(): float;

    public function customerEmail(): ?string;

    /**
     * Persist computed totals back to the entity columns.
     *
     * @param  array<string, float|string|null>  $totals
     */
    public function persistTotals(array $totals): void;

    /**
     * Return context used by ApplicabilityChecker (event_id, hotel_id, items, etc).
     *
     * @return array<string, mixed>
     */
    public function getPurchaseContext(): array;

    /**
     * Return per-unit items used by promo engine for quantity-based value types
     * (buy_x_get_y, bundle_price, tiered). One entry per logical buyable unit; if
     * the underlying item has quantity > 1, expand it into N identical rows so
     * the engine can pick a "cheapest" subset for free-item promos.
     *
     * @return array<int, array{line_key: string, item_id: int|string|null, item_type: string|null, category_id: int|null, unit_price: float, qty: int, taxable: bool, meta?: array<string, mixed>}>
     */
    public function purchaseItems(): array;
}
