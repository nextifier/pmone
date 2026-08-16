<?php

use App\Support\InputNormalizer;

it('normalizes emails to lowercase', function (?string $input, ?string $expected) {
    expect(InputNormalizer::email($input))->toBe($expected);
})->with([
    'uppercase' => ['JEBENNETT@HERSHEYS.COM', 'jebennett@hersheys.com'],
    'mixed case' => ['Yuana_Sari@cargill.com', 'yuana_sari@cargill.com'],
    'already lowercase' => ['ok@example.com', 'ok@example.com'],
    'padded' => ['  Padded@Example.com  ', 'padded@example.com'],
    'empty becomes null' => ['', null],
    'whitespace becomes null' => ['   ', null],
    'null stays null' => [null, null],
]);

it('title-cases person names only when input is single-case', function (?string $input, ?string $expected) {
    expect(InputNormalizer::personName($input))->toBe($expected);
})->with([
    'all caps' => ['ANI SETIYONINGRUM', 'Ani Setiyoningrum'],
    'all lowercase' => ['john edward bennett', 'John Edward Bennett'],
    'mixed case preserved' => ['McDonald', 'McDonald'],
    'particles preserved' => ['Erwan van der Berg', 'Erwan van der Berg'],
    'normal name untouched' => ['John Doe', 'John Doe'],
    'hyphenated caps' => ['ANNE-MARIE', 'Anne-Marie'],
    'apostrophe lowercase' => ["o'brien", "O'Brien"],
    'unicode caps' => ['JOSÉ GARCÍA', 'José García'],
    'multi space collapsed' => ['  JOHN   DOE  ', 'John Doe'],
    'digits only untouched' => ['12345', '12345'],
    'empty becomes null' => ['', null],
    'null stays null' => [null, null],
]);

it('trims org names without changing case', function (?string $input, ?string $expected) {
    expect(InputNormalizer::orgName($input))->toBe($expected);
})->with([
    'caps preserved' => ['PT GLOBAL NIAGA', 'PT GLOBAL NIAGA'],
    'whitespace collapsed' => ['  Hotel  Tentrem   Yogyakarta ', 'Hotel Tentrem Yogyakarta'],
    'empty becomes null' => ['', null],
    'null stays null' => [null, null],
]);

it('normalizes phones to international format', function (?string $input, ?string $expected) {
    expect(InputNormalizer::phone($input))->toBe($expected);
})->with([
    'local indonesian' => ['081234567890', '+6281234567890'],
    'separators stripped' => ['0812-3456 7890', '+6281234567890'],
    'already international' => ['+6281234567890', '+6281234567890'],
    'foreign international kept' => ['+14155552671', '+14155552671'],
    'empty becomes null' => ['', null],
    'null stays null' => [null, null],
]);

it('normalizes email lists and drops empty entries', function () {
    expect(InputNormalizer::emailList(['A@B.COM', '  ', 'ok@ok.com', null, 42]))
        ->toBe(['a@b.com', 'ok@ok.com'])
        ->and(InputNormalizer::emailList(null))->toBe([]);
});

it('normalizes phone lists and drops empty entries', function () {
    expect(InputNormalizer::phoneList(['0812 3456 7890', '', '+31612345678']))
        ->toBe(['+6281234567890', '+31612345678'])
        ->and(InputNormalizer::phoneList(null))->toBe([]);
});

it('dashes booth numbers before their final digit group', function (?string $input, ?string $expected) {
    expect(InputNormalizer::boothNumber($input))->toBe($expected);
})->with([
    'letter then digits' => ['8A81', '8A-81'],
    'single letter prefix' => ['B01', 'B-01'],
    'only the final group' => ['B1A01', 'B1A-01'],
    'two letters before the group' => ['B1BE6', 'B1BE-6'],
    'existing dash kept' => ['B-17B2', 'B-17B-2'],
    'lowercase uppercased' => ['B1b35', 'B1B-35'],
    'all lowercase uppercased' => ['b1a01', 'B1A-01'],
    'already dashed untouched' => ['A-04A', 'A-04A'],
    'trailing letter suffix' => ['A-10B', 'A-10B'],
    'double letter prefix' => ['AA-105', 'AA-105'],
    'digit in prefix' => ['6E-01', '6E-01'],
    'no digits at all' => ['A-NA', 'A-NA'],
    'per token, comma separated' => ['B1B19, B1B20', 'B1B-19, B1B-20'],
    'per token, slash separated' => ['B2E08/B2E09, B2D29/B2D31', 'B2E-08/B2E-09, B2D-29/B2D-31'],
    'ampersand token preserved' => ['A-22, &, A-23', 'A-22, &, A-23'],
    'inline ampersand preserved' => ['KM-13&14', 'KM-13&14'],
    'lowercase without digits uppercased' => ['a-na', 'A-NA'],
    'space around ampersand preserved' => ['A-22 & A-23', 'A-22 & A-23'],
    'comma spacing normalized' => ['B1B19,B1B20', 'B1B-19, B1B-20'],
    'whitespace collapsed' => ['  8A81   8A82 ', '8A-81 8A-82'],
    'empty becomes null' => ['', null],
    'whitespace becomes null' => ['   ', null],
    'null stays null' => [null, null],
]);

it('is idempotent for booth numbers', function (string $input) {
    $once = InputNormalizer::boothNumber($input);

    expect(InputNormalizer::boothNumber($once))->toBe($once);
})->with(['8A81', 'B1A01', 'B-17B2', 'B1B19, B1B20', 'B2E08/B2E09', 'A-22, &, A-23', 'KM-13&14']);

it('builds a zero-padded sort key from a booth number', function (?string $input, ?string $expected) {
    expect(InputNormalizer::boothSortKey($input))->toBe($expected);
})->with([
    'letter then digits' => ['8A81', '000008A000081'],
    'single letter prefix' => ['B01', 'B000001'],
    'compound prefix' => ['B1A01', 'B000001A000001'],
    'trailing letter suffix' => ['A-04A', 'A000004A'],
    'double letter prefix' => ['AA-105', 'AA000105'],
    'digit in prefix' => ['6E-01', '000006E000001'],
    'no digits at all' => ['A-NA', 'ANA'],
    'first token wins, comma separated' => ['B1B19, B1B20', 'B000001B000019'],
    'first token wins, slash separated' => ['B2E08/B2E09, B2D29/B2D31', 'B000002E000008'],
    'first token wins, inline ampersand' => ['KM-13&14', 'KM000013'],
    'first token wins, spaced ampersand' => ['A-22 & A-23', 'A000022'],
    'empty becomes null' => ['', null],
    'whitespace becomes null' => ['   ', null],
    'punctuation only becomes null' => ['---', null],
    'null stays null' => [null, null],
]);

it('gives booth numbers that differ only in formatting the same sort key', function () {
    $key = InputNormalizer::boothSortKey('B1B35');

    expect(InputNormalizer::boothSortKey('B1b35'))->toBe($key)
        ->and(InputNormalizer::boothSortKey('B1B-35'))->toBe($key)
        ->and(InputNormalizer::boothSortKey(' b1b-35 '))->toBe($key);
});

it('sorts booth numbers in physical order through the sort key', function () {
    $booths = ['A-10', 'B-1', 'A-2', 'A-1', '6E-05', 'CB-002', 'CB-001', 'Z-10'];

    usort($booths, fn ($a, $b) => strcmp(
        InputNormalizer::boothSortKey($a),
        InputNormalizer::boothSortKey($b),
    ));

    expect($booths)->toBe(['6E-05', 'A-1', 'A-2', 'A-10', 'B-1', 'CB-001', 'CB-002', 'Z-10']);
});

it('is idempotent for booth sort keys', function (string $input) {
    $once = InputNormalizer::boothSortKey($input);

    expect(InputNormalizer::boothSortKey($once))->toBe($once);
})->with(['8A81', 'B1A01', 'B01', 'A-NA', 'AA-105']);
