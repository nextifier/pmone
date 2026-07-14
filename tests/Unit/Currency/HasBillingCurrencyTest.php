<?php

use App\Models\Order;
use App\Services\Currency\CurrencyResolver;

it('formats USD amounts with a dollar prefix and two decimals', function () {
    $order = new Order(['currency' => 'USD']);

    expect($order->formatMoney(1234.5))->toBe('$1,234.50');
});

it('formats IDR amounts with a rupiah prefix and no decimals', function () {
    $order = new Order(['currency' => 'IDR']);

    expect($order->formatMoney(1234))->toBe('Rp 1.234');
});

it('falls back to the rupiah branch for null amounts and unknown currencies', function () {
    $idrOrder = new Order(['currency' => 'IDR']);
    $otherOrder = new Order(['currency' => 'SGD']);

    expect($idrOrder->formatMoney(null))->toBe('Rp 0');
    expect($otherOrder->formatMoney(100))->toBe('Rp 100');
});

it('converts to IDR with half-up rounding at two decimals', function () {
    $order = new Order(['currency' => 'USD', 'exchange_rate_to_idr' => 16250.505]);

    $convert = fn (float $amount): float => $this->convertToIdr($amount);

    expect($convert->call($order, 1.0))->toBe(16250.51);
});

it('converts identically for an IDR rate of one', function () {
    $order = new Order(['currency' => 'IDR', 'exchange_rate_to_idr' => 1]);

    $convert = fn (float $amount): float => $this->convertToIdr($amount);

    expect($convert->call($order, 3330000.0))->toBe(3330000.0);
});

it('resolves the generic override ahead of the country', function () {
    $resolver = new CurrencyResolver;

    expect($resolver->resolve('usd', 'Indonesia'))->toBe('USD');
});

it('treats an empty-string override as no override', function () {
    $resolver = new CurrencyResolver;

    expect($resolver->resolve('', 'Singapore'))->toBe('USD');
    expect($resolver->resolve('', 'Indonesia'))->toBe('IDR');
});

it('defaults to IDR when override and country are both missing', function () {
    $resolver = new CurrencyResolver;

    expect($resolver->resolve(null, null))->toBe('IDR');
});
