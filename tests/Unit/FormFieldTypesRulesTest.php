<?php

use App\Models\FormField;
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
    $field = new FormField;
    $field->type = 'email';
    $field->validation = ['required' => true];
    $field->options = [];
    $field->settings = [];

    expect(FormFieldTypes::rulesFor($field, 'answer'))
        ->toBe(FormFieldTypes::rulesForType('email', 'answer', true));

    $select = new FormField;
    $select->type = 'select';
    $select->validation = [];
    $select->options = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];
    $select->settings = [];

    expect(FormFieldTypes::rulesFor($select, 'answer'))
        ->toBe(['answer' => ['nullable', 'string', 'in:a,b']]);
});
