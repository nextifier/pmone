<?php

use App\Helpers\PhoneCountryHelper;

it('formats indonesian numbers to whatsapp digits (no plus)', function (string $input, string $expected) {
    expect(PhoneCountryHelper::toWhatsAppNumber($input))->toBe($expected);
})->with([
    'local 08 prefix' => ['08123456789', '628123456789'],
    'international with plus' => ['+628123456789', '628123456789'],
    'international without plus' => ['628123456789', '628123456789'],
    'with separators' => ['0812-3456-789', '628123456789'],
    'with spaces and parens' => ['+62 (812) 3456 789', '628123456789'],
    'empty string' => ['', ''],
]);
