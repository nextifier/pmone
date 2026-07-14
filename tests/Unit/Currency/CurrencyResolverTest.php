<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\ExchangeRate;
use App\Services\Currency\CurrencyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = app(CurrencyResolver::class);
});

dataset('indonesia_countries', [
    'Indonesia',
    'indonesia',
    '  Indonesia  ',
    'ID',
    'idn',
    ' Republik Indonesia ',
    'republic of indonesia',
]);

it('resolves Indonesian country variants to IDR', function (string $country) {
    expect($this->resolver->currencyForCountry($country))->toBe('IDR');
})->with('indonesia_countries');

it('resolves empty or null country to IDR', function () {
    expect($this->resolver->currencyForCountry(null))->toBe('IDR');
    expect($this->resolver->currencyForCountry(''))->toBe('IDR');
    expect($this->resolver->currencyForCountry('   '))->toBe('IDR');
});

it('resolves a foreign country to USD', function () {
    expect($this->resolver->currencyForCountry('Singapore'))->toBe('USD');
    expect($this->resolver->currencyForCountry('United States'))->toBe('USD');
});

it('uses the brand country when no override is set', function () {
    $brand = Brand::factory()->country('Singapore')->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => Event::factory(),
        'currency_override' => null,
    ]);

    expect($this->resolver->resolveForBrandEvent($brandEvent))->toBe('USD');
});

it('lets the override win over the brand country', function () {
    $brand = Brand::factory()->country('Singapore')->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => Event::factory(),
        'currency_override' => 'IDR',
    ]);

    expect($this->resolver->resolveForBrandEvent($brandEvent))->toBe('IDR');
});

it('returns rate 1 for IDR without touching the exchange table', function () {
    expect($this->resolver->exchangeRateToIdr('IDR'))->toBe(1.0);
});

it('returns the latest USD to IDR rate', function () {
    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['IDR' => 16250.5],
        'fetched_at' => now(),
    ]);

    expect($this->resolver->exchangeRateToIdr('USD'))->toBe(16250.5);
});

it('throws when the exchange table has no rate for a foreign currency', function () {
    $this->resolver->exchangeRateToIdr('USD');
})->throws(RuntimeException::class, 'Exchange rate unavailable');
