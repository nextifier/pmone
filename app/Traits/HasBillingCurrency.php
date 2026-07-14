<?php

namespace App\Traits;

/**
 * Billing-currency helpers shared by purchasable models that snapshot a
 * transaction currency + FX rate (exhibitor orders today; ticket orders and
 * hotel reservations when they adopt IDR/USD billing).
 *
 * The consuming model must expose a `currency` (string) attribute and an
 * `exchange_rate_to_idr` (numeric) attribute. Fillable/cast declarations stay
 * on each model so a column is never mass-assignable before its migration
 * exists.
 */
trait HasBillingCurrency
{
    /**
     * Formats a monetary amount using this model's currency for display in
     * emails and documents. USD renders with a "$" prefix and 2 decimals;
     * every other value (including null) falls back to Rupiah.
     */
    public function formatMoney(float|int|string|null $amount): string
    {
        $value = (float) $amount;

        return $this->currency === 'USD'
            ? '$'.number_format($value, 2)
            : 'Rp '.number_format($value, 0, ',', '.');
    }

    /**
     * Reporting-currency (IDR) equivalent of an amount, using the FX snapshot
     * frozen on this model at creation time. IDR models carry rate 1.
     */
    protected function convertToIdr(float $amount): float
    {
        return round($amount * (float) $this->exchange_rate_to_idr, 2, PHP_ROUND_HALF_UP);
    }
}
