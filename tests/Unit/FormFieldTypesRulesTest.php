<?php

use App\Models\CustomField;
use App\Support\FormFieldTypes;

it('builds the expected rules per field type', function () {
    expect(FormFieldTypes::rulesForType('email', 'answer', true))
        ->toBe(['answer' => ['required', 'string', 'email', 'max:255']]);

    expect(FormFieldTypes::rulesForType('text', 'answer', false))
        ->toBe(['answer' => ['nullable', 'string', 'max:65535']]);

    expect(FormFieldTypes::rulesForType('date', 'answer', true))
        ->toBe(['answer' => ['required', 'date_format:Y-m-d']]);

    expect(FormFieldTypes::rulesForType('select', 'answer', false, ['a', 'b']))
        ->toBe(['answer' => ['nullable', 'string', 'in:a,b']]);

    expect(FormFieldTypes::rulesForType('multi_select', 'answer', false, ['a', 'b']))
        ->toBe([
            'answer' => ['nullable', 'array'],
            'answer.*' => ['string', 'in:a,b'],
        ]);

    expect(FormFieldTypes::rulesForType('date_range', 'answer', false))
        ->toBe([
            'answer' => ['nullable', 'array'],
            'answer.start' => ['required_with:answer', 'date_format:Y-m-d'],
            'answer.end' => ['required_with:answer', 'date_format:Y-m-d', 'after_or_equal:answer.start'],
        ]);
});

it('formats stored business-matching values per type (unwrapping scalars)', function () {
    // Scalars are persisted wrapped in a single-element array.
    expect(FormFieldTypes::formatStoredValue('text', ['Acme Corp']))->toBe('Acme Corp');
    expect(FormFieldTypes::formatStoredValue('number', [25]))->toBe('25');
    expect(FormFieldTypes::formatStoredValue('select', ['Sales'], ['Tech', 'Sales']))->toBe('Sales');
    expect(FormFieldTypes::formatStoredValue('radio', ['Tech'], ['Tech', 'Sales']))->toBe('Tech');
    expect(FormFieldTypes::formatStoredValue('checkbox', [true]))->toBe('Yes');
    expect(FormFieldTypes::formatStoredValue('checkbox', [false]))->toBe('No');
    expect(FormFieldTypes::formatStoredValue('switch', [true]))->toBe('Yes');
    expect(FormFieldTypes::formatStoredValue('rating', [4]))->toBe('4');
    expect(FormFieldTypes::formatStoredValue('linear_scale', [5]))->toBe('5');
    expect(FormFieldTypes::formatStoredValue('color', ['#ff5500']))->toBe('#ff5500');
    expect(FormFieldTypes::formatStoredValue('date', ['2026-02-03']))->toBe('2026-02-03');

    // Multi-value types keep their array shape.
    expect(FormFieldTypes::formatStoredValue('multi_select', ['Tech', 'Marketing'], ['Tech', 'Sales', 'Marketing']))
        ->toBe('Tech, Marketing');
    expect(FormFieldTypes::formatStoredValue('tags', ['vue', 'php']))->toBe('vue, php');

    // Date range keeps its object shape.
    expect(FormFieldTypes::formatStoredValue('date_range', ['start' => '2026-01-01', 'end' => '2026-01-03']))
        ->toBe('2026-01-01 - 2026-01-03');

    // Empty answers render as a dash.
    expect(FormFieldTypes::formatStoredValue('text', null))->toBe('-');
    expect(FormFieldTypes::formatStoredValue('multi_select', [], ['Tech']))->toBe('-');
});

it('keeps rulesFor delegating identically to rulesForType', function () {
    $field = new CustomField;
    $field->type = 'email';
    $field->validation = ['required' => true];
    $field->options = [];
    $field->settings = [];

    expect(FormFieldTypes::rulesFor($field, 'answer'))
        ->toBe(FormFieldTypes::rulesForType('email', 'answer', true));

    $select = new CustomField;
    $select->type = 'select';
    $select->validation = [];
    $select->options = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];
    $select->settings = [];

    expect(FormFieldTypes::rulesFor($select, 'answer'))
        ->toBe(['answer' => ['nullable', 'string', 'in:a,b']]);
});

it('validates price as a bounded number', function () {
    expect(FormFieldTypes::rulesForType(CustomField::TYPE_PRICE, 'answer', true, [], ['min' => 0, 'max' => 500000000]))
        ->toBe(['answer' => ['required', 'numeric', 'min:0', 'max:500000000']]);

    expect(FormFieldTypes::rulesForType(CustomField::TYPE_PRICE, 'answer', false))
        ->toBe(['answer' => ['nullable', 'numeric']]);
});

it('validates price_range as an ordered pair of bounded numbers', function () {
    expect(FormFieldTypes::rulesForType(CustomField::TYPE_PRICE_RANGE, 'answer', false, [], ['min' => 0, 'max' => 500000000]))
        ->toBe([
            'answer' => ['nullable', 'array'],
            'answer.start' => ['required_with:answer', 'numeric', 'min:0', 'max:500000000'],
            'answer.end' => ['required_with:answer', 'numeric', 'min:0', 'max:500000000', 'gte:answer.start'],
        ]);
});

it('formats money values with a currency symbol and thousands separators', function () {
    expect(FormFieldTypes::formatValueForType(CustomField::TYPE_PRICE, 20000000))->toBe('Rp 20,000,000');
    expect(FormFieldTypes::formatValueForType(CustomField::TYPE_PRICE_RANGE, ['start' => 20000000, 'end' => 75000000]))
        ->toBe('Rp 20,000,000 - Rp 75,000,000');
    expect(FormFieldTypes::formatValueForType(CustomField::TYPE_PRICE_RANGE, ['start' => null, 'end' => null]))->toBe('-');

    // The symbol follows the field's own setting, and an empty one is dropped.
    expect(FormFieldTypes::formatValueForType(CustomField::TYPE_PRICE, 1500, [], ['currency' => 'USD']))
        ->toBe('USD 1,500');
    expect(FormFieldTypes::formatValueForType(CustomField::TYPE_PRICE, 1500, [], ['currency' => '']))
        ->toBe('1,500');
});

it('treats price_range as an object-range type', function () {
    expect(FormFieldTypes::OBJECT_RANGE_TYPES)->toContain(CustomField::TYPE_PRICE_RANGE)
        ->and(CustomField::allowedTypesFor(CustomField::CONTEXT_BRAND))
        ->toContain(CustomField::TYPE_PRICE)
        ->toContain(CustomField::TYPE_PRICE_RANGE);
});
