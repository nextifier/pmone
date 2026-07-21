<?php

use App\Models\CustomField;
use App\Models\Event;
use App\Models\Project;
use App\Support\CustomFieldValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passes when every required answer is present and valid', function () {
    $event = Event::factory()->create();
    $field = CustomField::factory()->ticketRegistration($event)->type(CustomField::TYPE_SELECT)->required()->create();

    $errors = CustomFieldValidation::errorsFor(
        collect([$field]),
        [$field->ulid => 'option-1'],
        'registration.responses',
    );

    expect($errors)->toBe([]);
});

it('fails required fields and treats blank strings as absent', function () {
    $event = Event::factory()->create();
    $field = CustomField::factory()->ticketRegistration($event)->required()->create();

    $missing = CustomFieldValidation::errorsFor(collect([$field]), [], 'registration.responses');
    $blank = CustomFieldValidation::errorsFor(collect([$field]), [$field->ulid => ''], 'registration.responses');

    expect($missing)->toHaveKey('registration.responses.'.$field->ulid)
        ->and($blank)->toHaveKey('registration.responses.'.$field->ulid);
});

it('rejects answers outside the option list including years preset fields', function () {
    $event = Event::factory()->create();

    $select = CustomField::factory()->ticketRegistration($event)->type(CustomField::TYPE_SELECT)->create();
    $year = CustomField::factory()->ticketRegistration($event)->create([
        'type' => CustomField::TYPE_SELECT,
        'options' => null,
        'settings' => ['options_preset' => 'years'],
    ]);

    $errors = CustomFieldValidation::errorsFor(
        collect([$select, $year]),
        [$select->ulid => 'not-an-option', $year->ulid => '1850'],
    );

    expect($errors)->toHaveKey('responses.'.$select->ulid)
        ->and($errors)->toHaveKey('responses.'.$year->ulid);

    $valid = CustomFieldValidation::errorsFor(
        collect([$select, $year]),
        [$select->ulid => 'option-2', $year->ulid => '1995'],
    );

    expect($valid)->toBe([]);
});

it('keys errors by id or brand key when requested', function () {
    $event = Event::factory()->create();
    $project = Project::factory()->create();

    $byId = CustomField::factory()->businessMatching($event)->required()->create();
    $byKey = CustomField::factory()->brand($project)->required()->create(['key' => 'founded']);

    $idErrors = CustomFieldValidation::errorsFor(collect([$byId]), [], 'business_matching.responses', 'id');
    $keyErrors = CustomFieldValidation::errorsFor(collect([$byKey]), [], 'project_custom_fields', 'key');

    expect($idErrors)->toHaveKey('business_matching.responses.'.$byId->id)
        ->and($keyErrors)->toHaveKey('project_custom_fields.founded');
});

it('skips section fields entirely', function () {
    $event = Event::factory()->create();
    $section = CustomField::factory()->businessMatching($event)->type(CustomField::TYPE_SECTION)->required()->create();

    expect(CustomFieldValidation::errorsFor(collect([$section]), []))->toBe([]);
});

it('validates object-range answers through the nested start and end rules', function () {
    $event = Event::factory()->create();

    $monthRange = CustomField::factory()->ticketRegistration($event)->type(CustomField::TYPE_MONTH_RANGE)->create();
    $sliderRange = CustomField::factory()->ticketRegistration($event)->type(CustomField::TYPE_SLIDER_RANGE)->create();

    $valid = CustomFieldValidation::errorsFor(
        collect([$monthRange, $sliderRange]),
        [
            $monthRange->ulid => ['start' => '2026-03', 'end' => '2026-07'],
            $sliderRange->ulid => ['start' => 10, 'end' => 40],
        ],
    );

    expect($valid)->toBe([]);

    $inverted = CustomFieldValidation::errorsFor(
        collect([$monthRange, $sliderRange]),
        [
            $monthRange->ulid => ['start' => '2026-07', 'end' => '2026-03'],
            $sliderRange->ulid => ['start' => 60, 'end' => 10],
        ],
    );

    expect($inverted)->toHaveKey('responses.'.$monthRange->ulid)
        ->and($inverted)->toHaveKey('responses.'.$sliderRange->ulid);

    $badFormat = CustomFieldValidation::errorsFor(
        collect([$monthRange]),
        [$monthRange->ulid => ['start' => '03-2026', 'end' => '2026-07']],
    );

    expect($badFormat)->toHaveKey('responses.'.$monthRange->ulid);
});

it('validates multi-select answers item by item', function () {
    $event = Event::factory()->create();
    $field = CustomField::factory()->businessMatching($event)->type(CustomField::TYPE_MULTI_SELECT)->create();

    $bad = CustomFieldValidation::errorsFor(collect([$field]), [$field->ulid => ['option-1', 'bogus']]);
    $good = CustomFieldValidation::errorsFor(collect([$field]), [$field->ulid => ['option-1', 'option-3']]);

    expect($bad)->toHaveKey('responses.'.$field->ulid)
        ->and($good)->toBe([]);
});
