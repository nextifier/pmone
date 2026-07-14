<?php

namespace App\Services\Currency;

use App\Models\BrandEvent;
use App\Models\ExchangeRate;
use RuntimeException;

/**
 * Resolves the billing (transaction) currency for a purchasable and the IDR
 * reporting exchange rate. Currency is ALWAYS resolved server-side, never taken
 * from a request payload.
 */
class CurrencyResolver
{
    /**
     * Free-text country values that map to IDR billing.
     *
     * @var list<string>
     */
    private const IDR_COUNTRY_ALIASES = [
        'indonesia',
        'id',
        'idn',
        'republik indonesia',
        'republic of indonesia',
    ];

    /**
     * Resolve a billing currency from a manual override and a free-text
     * country: the override wins, otherwise the country decides. An empty
     * string counts as "no override".
     */
    public function resolve(?string $override, ?string $country): string
    {
        if (! empty($override)) {
            return strtoupper($override);
        }

        return $this->currencyForCountry($country);
    }

    /**
     * Resolve the billing currency for a brand event: manual override wins,
     * otherwise it is derived from the brand's country.
     */
    public function resolveForBrandEvent(BrandEvent $brandEvent): string
    {
        if (! empty($brandEvent->currency_override)) {
            return $this->resolve($brandEvent->currency_override, null);
        }

        $brand = $brandEvent->relationLoaded('brand') ? $brandEvent->brand : $brandEvent->brand()->first();

        return $this->resolve(null, $brand?->address['country'] ?? null);
    }

    /**
     * Map a free-text country string to a billing currency. Empty/null country
     * falls back to IDR (most exhibitors are domestic); any non-Indonesian
     * country bills in USD.
     */
    public function currencyForCountry(?string $country): string
    {
        if ($country === null || trim($country) === '') {
            return 'IDR';
        }

        $normalized = strtolower(trim($country));

        return in_array($normalized, self::IDR_COUNTRY_ALIASES, true) ? 'IDR' : 'USD';
    }

    /**
     * Reporting exchange rate (IDR per 1 unit of the given currency). IDR is 1.
     * For foreign currencies the latest fetched rate is used even if stale; an
     * empty exchange-rate table raises a RuntimeException that controllers turn
     * into a 422.
     *
     * @throws RuntimeException when no rate is available for a foreign currency
     */
    public function exchangeRateToIdr(string $currency): float
    {
        $currency = strtoupper($currency);

        if ($currency === 'IDR') {
            return 1.0;
        }

        $rate = ExchangeRate::getLatest($currency)?->getRate('IDR');

        if ($rate === null) {
            throw new RuntimeException('Exchange rate unavailable, please try again later.');
        }

        return (float) $rate;
    }
}
