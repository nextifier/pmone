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
