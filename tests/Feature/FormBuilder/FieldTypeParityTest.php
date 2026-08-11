<?php

use App\Support\FormFieldTypes;

/**
 * The public form validates each step in the browser before letting the visitor
 * advance, which puts a second copy of the per-type rules in
 * `custom-field/validation.ts`. The server stays authoritative, but a type
 * added on only one side is a silent hole: the browser would wave it through
 * with no rules at all, or the renderer would offer a type the validator has
 * never heard of. These tests read the TypeScript so that hole cannot open
 * without a red test.
 */
$componentPath = fn (string $file) => base_path("frontend/app/components/ui/custom-field/{$file}");

/**
 * @return array<int, string>
 */
$typeMetaKeys = function () use ($componentPath): array {
    $source = file_get_contents($componentPath('core.ts'));

    preg_match('/export const TYPE_META[^=]*=\s*\{(.*?)\n\};/s', $source, $block);

    expect($block[1] ?? null)->not->toBeNull('TYPE_META block not found in core.ts');

    preg_match_all('/^\s{2}([a-z_]+):/m', $block[1], $matches);

    return $matches[1];
};

it('declares the same field types in core.ts as the backend registry', function () use ($typeMetaKeys) {
    $php = FormFieldTypes::all();
    $ts = $typeMetaKeys();

    sort($php);
    sort($ts);

    expect($ts)->toBe($php);
});

it('handles every backend field type in the client-side validator', function () use ($componentPath) {
    $source = file_get_contents($componentPath('validation.ts'));

    preg_match('/export function validateFieldValue\((.*?)\n\}\n/s', $source, $body);

    expect($body[1] ?? null)->not->toBeNull('validateFieldValue not found in validation.ts');

    $unhandled = array_values(array_filter(
        FormFieldTypes::all(),
        fn (string $type) => ! str_contains($body[1], "\"{$type}\""),
    ));

    expect($unhandled)->toBe([], 'These types have no branch in validateFieldValue: '.implode(', ', $unhandled));
});
