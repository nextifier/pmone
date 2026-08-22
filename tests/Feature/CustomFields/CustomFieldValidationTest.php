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

it('enforces the selection bounds the editor can now set', function () {
    $event = Event::factory()->create();
    $field = CustomField::factory()->businessMatching($event)->type(CustomField::TYPE_MULTI_SELECT)->create([
        'validation' => ['required' => true, 'min_selections' => 2, 'max_selections' => 2],
    ]);

    $tooFew = CustomFieldValidation::errorsFor(collect([$field]), [$field->ulid => ['option-1']]);
    $tooMany = CustomFieldValidation::errorsFor(
        collect([$field]),
        [$field->ulid => ['option-1', 'option-2', 'option-3']],
    );
    $justRight = CustomFieldValidation::errorsFor(
        collect([$field]),
        [$field->ulid => ['option-1', 'option-2']],
    );

    expect($tooFew)->toHaveKey('responses.'.$field->ulid)
        ->and($tooMany)->toHaveKey('responses.'.$field->ulid)
        ->and($justRight)->toBe([]);
});

/**
 * The province/city datasets cover Indonesia only, so the renderer withdraws
 * those fields entirely when the country is anything else. A field the buyer
 * was never shown must not be required of them.
 */
function locationTrio(Event $event, bool $required = true): array
{
    $country = CustomField::factory()->ticketRegistration($event)->create([
        'type' => CustomField::TYPE_COUNTRY,
        'system_key' => 'country',
        'validation' => [],
    ]);

    $province = CustomField::factory()->ticketRegistration($event)->create([
        'type' => CustomField::TYPE_PROVINCE,
        'system_key' => 'province',
        'settings' => ['depends_on' => 'country'],
        'validation' => ['required' => $required],
    ]);

    $city = CustomField::factory()->ticketRegistration($event)->create([
        'type' => CustomField::TYPE_CITY,
        'system_key' => 'city',
        'settings' => ['depends_on' => 'province'],
        'validation' => ['required' => $required],
    ]);

    return [$country, $province, $city];
}

it('does not require province or city when the country is outside Indonesia', function () {
    $event = Event::factory()->create();
    [$country, $province, $city] = locationTrio($event);

    $errors = CustomFieldValidation::errorsFor(
        collect([$country, $province, $city]),
        [$country->ulid => 'Japan'],
        'registration.responses',
    );

    expect($errors)->toBe([]);
});

it('does not require province or city when the country has not been answered', function () {
    $event = Event::factory()->create();
    [$country, $province, $city] = locationTrio($event);

    $errors = CustomFieldValidation::errorsFor(
        collect([$country, $province, $city]),
        [],
        'registration.responses',
    );

    expect($errors)->toBe([]);
});

it('still requires province and city once the country is Indonesia', function () {
    $event = Event::factory()->create();
    [$country, $province, $city] = locationTrio($event);

    $errors = CustomFieldValidation::errorsFor(
        collect([$country, $province, $city]),
        [$country->ulid => 'Indonesia'],
        'registration.responses',
    );

    expect($errors)->toHaveKey('registration.responses.'.$province->ulid)
        ->and($errors)->toHaveKey('registration.responses.'.$city->ulid);
});

it('still rejects a city that does not belong to the chosen province', function () {
    $event = Event::factory()->create();
    [$country, $province, $city] = locationTrio($event, required: false);

    $errors = CustomFieldValidation::errorsFor(
        collect([$country, $province, $city]),
        [
            $country->ulid => 'Indonesia',
            $province->ulid => 'Bali',
            $city->ulid => 'Kabupaten Bogor',
        ],
        'registration.responses',
    );

    expect($errors)->toHaveKey('registration.responses.'.$city->ulid);
});
