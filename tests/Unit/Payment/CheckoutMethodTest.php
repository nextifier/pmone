<?php

use App\Enums\Payment\CheckoutMethod;

test('values lists every case', function () {
    expect(CheckoutMethod::values())
        ->toHaveCount(3)
        ->toContain('sessions_payment_link', 'sessions_components', 'payment_link_legacy');
});

test('availableValues lists every case once components is enabled', function () {
    expect(CheckoutMethod::availableValues())
        ->toContain('sessions_payment_link', 'sessions_components', 'payment_link_legacy')
        ->toHaveCount(3);
});

test('all three checkout methods are available', function () {
    expect(CheckoutMethod::SessionsComponents->available())->toBeTrue();
    expect(CheckoutMethod::SessionsPaymentLink->available())->toBeTrue();
    expect(CheckoutMethod::PaymentLinkLegacy->available())->toBeTrue();
});

test('default is sessions payment link', function () {
    expect(CheckoutMethod::default())->toBe(CheckoutMethod::SessionsPaymentLink);
});

test('every case exposes a non-empty label and description', function () {
    foreach (CheckoutMethod::cases() as $method) {
        expect($method->label())->toBeString()->not->toBeEmpty();
        expect($method->description())->toBeString()->not->toBeEmpty();
    }
});
