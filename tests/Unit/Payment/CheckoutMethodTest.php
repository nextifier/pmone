<?php

use App\Enums\Payment\CheckoutMethod;

test('values lists every case', function () {
    expect(CheckoutMethod::values())
        ->toHaveCount(2)
        ->toContain('payment_link_sessions', 'payment_link_legacy');
});

test('availableValues lists every case', function () {
    expect(CheckoutMethod::availableValues())
        ->toContain('payment_link_sessions', 'payment_link_legacy')
        ->toHaveCount(2);
});

test('both checkout methods are available', function () {
    expect(CheckoutMethod::PaymentLinkSessions->available())->toBeTrue();
    expect(CheckoutMethod::PaymentLinkLegacy->available())->toBeTrue();
});

test('default is payment link sessions', function () {
    expect(CheckoutMethod::default())->toBe(CheckoutMethod::PaymentLinkSessions);
});

test('labels reflect the renamed Payment Link naming pattern', function () {
    expect(CheckoutMethod::PaymentLinkSessions->label())->toBe('Payment Link - Sessions');
    expect(CheckoutMethod::PaymentLinkLegacy->label())->toBe('Payment Link - Legacy');
});

test('every case exposes a non-empty label and description', function () {
    foreach (CheckoutMethod::cases() as $method) {
        expect($method->label())->toBeString()->not->toBeEmpty();
        expect($method->description())->toBeString()->not->toBeEmpty();
    }
});
