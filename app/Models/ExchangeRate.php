<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $base_currency
 * @property array<string, float> $rates
 * @property \Illuminate\Support\Carbon|null $api_updated_at
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExchangeRate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'base_currency',
        'rates',
        'api_updated_at',
        'fetched_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rates' => 'array',
            'api_updated_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Scope for a specific base currency.
     */
    public function scopeForCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('base_currency', strtoupper($currency));
    }

    /**
     * Get the latest exchange rate record for a currency.
     */
    public static function getLatest(string $baseCurrency = 'USD'): ?self
    {
        return self::forCurrency($baseCurrency)
            ->latest('fetched_at')
            ->first();
    }

    /**
     * Get a specific rate from the rates array.
     */
    public function getRate(string $currency): ?float
    {
        $currency = strtoupper($currency);

        return $this->rates[$currency] ?? null;
    }

    /**
     * Convert an amount from base currency to target currency.
     */
    public function convert(float $amount, string $toCurrency): ?float
    {
        $rate = $this->getRate($toCurrency);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Convert an amount between any two currencies.
     */
    public function convertBetween(float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        $fromRate = $this->getRate($fromCurrency);
        $toRate = $this->getRate($toCurrency);

        if ($fromRate === null || $toRate === null) {
            return null;
        }

        // Convert to base currency first, then to target
        $inBase = $amount / $fromRate;

        return $inBase * $toRate;
    }

    /**
     * Get all available currency codes.
     *
     * @return array<string>
     */
    public function getCurrencyCodes(): array
    {
        return array_keys($this->rates);
    }

    /**
     * Check if exchange rate data is stale.
     */
    public function isStale(?int $minutesTtl = null): bool
    {
        $ttl = $minutesTtl ?? config('services.exchange_rate.cache_ttl_minutes', 120);

        return $this->fetched_at->addMinutes($ttl)->isPast();
    }
}
