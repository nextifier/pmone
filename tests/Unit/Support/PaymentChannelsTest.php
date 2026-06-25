<?php

use App\Support\PaymentChannels;

it('exposes a catalog of selectable canonical channels with labels and logos', function () {
    $catalog = PaymentChannels::catalog();

    expect($catalog)->not->toBeEmpty();

    foreach ($catalog as $entry) {
        expect($entry)->toHaveKeys(['code', 'label', 'group', 'logo_url']);
        expect($entry['code'])->toBe(strtoupper($entry['code']));
        expect($entry['logo_url'])->toStartWith('/img/payment-methods/');
        expect(PaymentChannels::isValid($entry['code']))->toBeTrue();
    }

    // Card brands collapse into a single CREDIT_CARD entry - no per-brand rows.
    $codes = array_column($catalog, 'code');
    expect($codes)->toContain('CREDIT_CARD')
        ->not->toContain('VISA')
        ->not->toContain('MASTERCARD');
});

it('validates canonical codes and rejects unknown ones', function () {
    expect(PaymentChannels::isValid('BCA'))->toBeTrue();
    expect(PaymentChannels::isValid('bca'))->toBeTrue();
    expect(PaymentChannels::isValid('CREDIT_CARD'))->toBeTrue();
    expect(PaymentChannels::isValid('FOO'))->toBeFalse();
    expect(PaymentChannels::isValid('VISA'))->toBeFalse();
});

it('maps canonical codes to Xendit Sessions channel codes (cards -> CARDS, deduped)', function () {
    expect(PaymentChannels::toXenditSessionsCodes(['VISA', 'MASTERCARD', 'BCA']))
        ->toBe(['CARDS', 'BCA']);

    expect(PaymentChannels::toXenditSessionsCodes(['CREDIT_CARD', 'QRIS', 'OVO']))
        ->toBe(['CARDS', 'QRIS', 'OVO']);

    expect(PaymentChannels::toXenditSessionsCodes(['DD_BRI']))
        ->toBe(['BRI_DIRECT_DEBIT']);
});

it('maps canonical codes to Xendit Invoice payment methods (cards -> CREDIT_CARD)', function () {
    expect(PaymentChannels::toXenditInvoiceCodes(['VISA', 'BCA']))
        ->toBe(['CREDIT_CARD', 'BCA']);

    expect(PaymentChannels::toXenditInvoiceCodes(['CREDIT_CARD', 'CREDIT_CARD', 'QRIS']))
        ->toBe(['CREDIT_CARD', 'QRIS']);
});

it('maps canonical codes to Midtrans enabled_payments and drops unsupported ones', function () {
    expect(PaymentChannels::toMidtransEnabledPayments(['BCA', 'GOPAY']))
        ->toBe(['bca_va', 'gopay']);

    expect(PaymentChannels::toMidtransEnabledPayments(['VISA', 'MASTERCARD']))
        ->toBe(['credit_card']);

    expect(PaymentChannels::toMidtransEnabledPayments(['MANDIRI', 'QRIS']))
        ->toBe(['echannel', 'qris']);

    // OVO/DANA have no Snap equivalent -> dropped (here that means "no restriction").
    expect(PaymentChannels::toMidtransEnabledPayments(['OVO', 'DANA']))->toBe([]);
});

it('exposes the Midtrans-supported canonical subset', function () {
    $supported = PaymentChannels::midtransSupportedCodes();

    expect($supported)->toContain('BCA', 'CREDIT_CARD', 'QRIS', 'GOPAY')
        ->not->toContain('OVO', 'DANA', 'BJB');
});

it('drops unknown codes when mapping', function () {
    expect(PaymentChannels::toXenditSessionsCodes(['FOO', 'BCA', 'BOGUS']))->toBe(['BCA']);
    expect(PaymentChannels::toXenditInvoiceCodes(['FOO']))->toBe([]);
    expect(PaymentChannels::toXenditSessionsCodes([]))->toBe([]);
});

it('intersects the catalog with a gateway enabled-channel list', function () {
    $catalog = PaymentChannels::catalogForEnabled(['BCA', 'VISA', 'QRIS', 'UNKNOWN_CHANNEL']);
    $codes = array_column($catalog, 'code');

    // VISA is an alias of the CREDIT_CARD entry; UNKNOWN is ignored.
    expect($codes)->toEqualCanonicalizing(['BCA', 'CREDIT_CARD', 'QRIS']);

    expect(PaymentChannels::catalogForEnabled([]))->toBe([]);
});
