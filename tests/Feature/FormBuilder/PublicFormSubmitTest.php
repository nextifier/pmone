<?php

use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fb_submit_field(Form $form, string $type, array $overrides = []): CustomField
{
    return CustomField::factory()->type($type)->create(array_merge(
        ['form_id' => $form->id, 'label' => "Field {$type}"],
        $overrides
    ));
}

dataset('valid field values', [
    'text' => ['text', 'Hello world'],
    'textarea' => ['textarea', "Line one\nLine two"],
    'rich_text' => ['rich_text', '<p>Hello <strong>world</strong></p>'],
    'email' => ['email', 'user@example.com'],
    'number' => ['number', 42],
    'phone' => ['phone', '+628123456789'],
    'url' => ['url', 'https://example.com'],
    'date' => ['date', '2026-07-01'],
    'time' => ['time', '09:15'],
    'datetime' => ['datetime', '2026-07-01 10:30'],
    'date_range' => ['date_range', ['start' => '2026-07-01', 'end' => '2026-07-03']],
    'select' => ['select', 'option-1'],
    'multi_select' => ['multi_select', ['option-1', 'option-2']],
    'checkbox' => ['checkbox', true],
    'checkbox_group' => ['checkbox_group', ['option-2']],
    'radio' => ['radio', 'option-3'],
    'tags' => ['tags', ['vue', 'laravel']],
    'switch' => ['switch', true],
    'slider' => ['slider', 55],
    'rating' => ['rating', 5],
    'linear_scale' => ['linear_scale', 4],
    'color' => ['color', '#2563eb'],
    'country' => ['country', 'Indonesia'],
]);

dataset('invalid field values', [
    'email' => ['email', 'not-an-email'],
    'number' => ['number', 'abc'],
    'url' => ['url', 'not a url'],
    'date' => ['date', '15-01-2026'],
    'time' => ['time', '25:61'],
    'datetime' => ['datetime', '2026-07-01'],
    'date_range' => ['date_range', ['start' => '2026-07-05', 'end' => '2026-07-01']],
    'select' => ['select', 'not-an-option'],
    'multi_select' => ['multi_select', ['not-an-option']],
    'checkbox_group' => ['checkbox_group', ['nope']],
    'radio' => ['radio', 'nope'],
    'tags' => ['tags', 'not-an-array'],
    'slider' => ['slider', 9999],
    'rating' => ['rating', 9],
    'linear_scale' => ['linear_scale', 99],
    'color' => ['color', 'blue'],
    'file' => ['file', 'no-prefix-token'],
]);

it('accepts a valid value for each field type', function (string $type, mixed $value) {
    $form = Form::factory()->published()->create();
    $field = fb_submit_field($form, $type);

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [$field->ulid => $value],
    ])->assertCreated();

    $stored = FormResponse::where('form_id', $form->id)->first();
    expect($stored->response_data[$field->ulid])->toEqual($value);
})->with('valid field values');

it('rejects an invalid value for each field type', function (string $type, mixed $value) {
    $form = Form::factory()->published()->create();
    $field = fb_submit_field($form, $type);

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [$field->ulid => $value],
    ])->assertUnprocessable();
})->with('invalid field values');

it('enforces required fields', function () {
    $form = Form::factory()->published()->create();
    $field = fb_submit_field($form, 'text', ['validation' => ['required' => true]]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
    ])->assertUnprocessable();
});

it('requires a checked value for required checkboxes', function () {
    $form = Form::factory()->published()->create();
    $field = fb_submit_field($form, 'checkbox', ['validation' => ['required' => true]]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [$field->ulid => false],
    ])->assertUnprocessable();
});

it('enforces min and max selections', function () {
    $form = Form::factory()->published()->create();
    $field = fb_submit_field($form, 'multi_select', [
        'validation' => ['min_selections' => 2, 'max_selections' => 2],
    ]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [$field->ulid => ['option-1']],
    ])->assertUnprocessable();

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [$field->ulid => ['option-1', 'option-2', 'option-3']],
    ])->assertUnprocessable();
});

it('enforces respondent email when require_email is on', function () {
    $form = Form::factory()->published()->create([
        'settings' => ['require_email' => true],
    ]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
    ])->assertUnprocessable();

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        'respondent_email' => 'user@example.com',
    ])->assertCreated();
});

it('strips unknown ulids and section values from stored data', function () {
    $form = Form::factory()->published()->create();
    $textField = fb_submit_field($form, 'text');
    $sectionField = fb_submit_field($form, 'section');

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [
            $textField->ulid => 'Kept',
            $sectionField->ulid => 'Should be stripped',
            'unknown-ulid' => 'Injected',
        ],
    ])->assertCreated();

    $stored = FormResponse::where('form_id', $form->id)->first();
    expect($stored->response_data)->toBe([$textField->ulid => 'Kept']);
});

it('returns the confirmation message and redirect url', function () {
    $form = Form::factory()->published()->create([
        'settings' => [
            'confirmation_message' => 'Custom thanks!',
            'redirect_url' => 'https://example.com/thanks',
        ],
    ]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertCreated()
        ->assertJson([
            'message' => 'Custom thanks!',
            'redirect_url' => 'https://example.com/thanks',
        ]);
});

it('rejects submissions when the response limit is reached', function () {
    $form = Form::factory()->published()->withResponseLimit(1)->create();
    FormResponse::factory()->create(['form_id' => $form->id]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertForbidden();
});

it('rejects submissions for closed forms', function () {
    $form = Form::factory()->published()->create(['closes_at' => now()->subHour()]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertForbidden();
});

function fb_honeypot_token(int $ageSeconds = 10): string
{
    $timestamp = time() - $ageSeconds;
    $random1 = bin2hex(random_bytes(4));
    $random2 = bin2hex(random_bytes(4));

    return base64_encode("{$random1}_{$timestamp}_{$random2}");
}

it('rejects submissions with a filled honeypot field', function () {
    $form = Form::factory()->published()->create();

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        'website' => 'https://spam.example.com',
    ])->assertUnprocessable();
});

it('rejects submissions that are too fast for the honeypot timer', function () {
    $form = Form::factory()->published()->create();

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        '_token_time' => fb_honeypot_token(0),
    ])->assertUnprocessable();
});

it('accepts submissions with a valid honeypot token', function () {
    $form = Form::factory()->published()->create();

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        'website' => '',
        '_token_time' => fb_honeypot_token(10),
    ])->assertCreated();
});

it('rate limits rapid submissions per ip', function () {
    $form = Form::factory()->published()->create();

    foreach (range(1, 10) as $i) {
        $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
            ->assertCreated();
    }

    $this->postJson("/api/public/forms/{$form->slug}/submit", ['responses' => []])
        ->assertTooManyRequests();
});
