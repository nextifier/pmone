<?php

use App\Helpers\PhoneCountryHelper;

// getCountryFromPhone tests

it('detects Indonesia from +62 prefix', function () {
    $result = PhoneCountryHelper::getCountryFromPhone('+6281234567890');

    expect($result)->toBe(['code' => 'ID', 'name' => 'Indonesia']);
});

it('detects China from +86 prefix', function () {
    $result = PhoneCountryHelper::getCountryFromPhone('+8613800138000');

    expect($result)->toBe(['code' => 'CN', 'name' => 'China']);
});

it('detects United States from +1 prefix', function () {
    $result = PhoneCountryHelper::getCountryFromPhone('+12025551234');

    expect($result)->toBe(['code' => 'US', 'name' => 'United States']);
});

it('detects longer prefix before shorter one', function () {
    // +62 (Indonesia) should match before +6 (no match)
    $result = PhoneCountryHelper::getCountryFromPhone('+628123456789');
    expect($result['code'])->toBe('ID');

    // +852 (Hong Kong) should match before +85 (no match)
    $result = PhoneCountryHelper::getCountryFromPhone('+85212345678');
    expect($result['code'])->toBe('HK');
});

it('returns null for unknown prefix', function () {
    expect(PhoneCountryHelper::getCountryFromPhone('+99912345'))->toBeNull();
});

it('returns null for empty string', function () {
    expect(PhoneCountryHelper::getCountryFromPhone(''))->toBeNull();
});

// getCountryName tests

it('returns country name for valid phone', function () {
    expect(PhoneCountryHelper::getCountryName('+6281234567890'))->toBe('Indonesia');
    expect(PhoneCountryHelper::getCountryName('+81312345678'))->toBe('Japan');
});

it('returns null country name for invalid phone', function () {
    expect(PhoneCountryHelper::getCountryName('+99912345'))->toBeNull();
});

// normalizePhoneNumber tests

it('keeps phone with + prefix as-is after cleaning separators', function () {
    expect(PhoneCountryHelper::normalizePhoneNumber('+62 812 3456 789'))->toBe('+628123456789');
    expect(PhoneCountryHelper::normalizePhoneNumber('+62-812-3456-789'))->toBe('+628123456789');
    expect(PhoneCountryHelper::normalizePhoneNumber('+62.812.3456.789'))->toBe('+628123456789');
    expect(PhoneCountryHelper::normalizePhoneNumber('+62(812)3456789'))->toBe('+628123456789');
});

it('converts local Indonesian format (0) to +62', function () {
    expect(PhoneCountryHelper::normalizePhoneNumber('08123456789'))->toBe('+628123456789');
    expect(PhoneCountryHelper::normalizePhoneNumber('081 234 567 89'))->toBe('+628123456789');
});

it('prepends + to 62 prefix with enough digits', function () {
    expect(PhoneCountryHelper::normalizePhoneNumber('628123456789'))->toBe('+628123456789');
    expect(PhoneCountryHelper::normalizePhoneNumber('6281234567890'))->toBe('+6281234567890');
});

it('handles already normalized numbers', function () {
    expect(PhoneCountryHelper::normalizePhoneNumber('+628123456789'))->toBe('+628123456789');
});

it('returns empty string for empty input', function () {
    expect(PhoneCountryHelper::normalizePhoneNumber(''))->toBe('');
    expect(PhoneCountryHelper::normalizePhoneNumber('  '))->toBe('');
});

it('prepends + for known international prefix without +', function () {
    // China: 86
    expect(PhoneCountryHelper::normalizePhoneNumber('8613800138000'))->toBe('+8613800138000');
    // Japan: 81
    expect(PhoneCountryHelper::normalizePhoneNumber('81312345678'))->toBe('+81312345678');
});

// Integration: normalizePhoneNumber + getCountryFromPhone

it('detects country after normalizing local Indonesian number', function () {
    $normalized = PhoneCountryHelper::normalizePhoneNumber('08123456789');
    $country = PhoneCountryHelper::getCountryFromPhone($normalized);

    expect($country)->toBe(['code' => 'ID', 'name' => 'Indonesia']);
});

it('detects country after normalizing number without + prefix', function () {
    $normalized = PhoneCountryHelper::normalizePhoneNumber('628123456789');
    $country = PhoneCountryHelper::getCountryFromPhone($normalized);

    expect($country)->toBe(['code' => 'ID', 'name' => 'Indonesia']);
});
