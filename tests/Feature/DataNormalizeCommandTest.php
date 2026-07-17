<?php

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedDirtyReservation(): Reservation
{
    $reservation = Reservation::factory()->create();

    DB::table('reservations')->where('id', $reservation->id)->update([
        'guest_name' => 'JOHN EDWARD BENNETT',
        'guest_email' => 'JEBENNETT@HERSHEYS.COM',
        'guest_phone' => '0812 3456 7890',
    ]);

    return $reservation;
}

it('reports changes without writing anything on dry run', function () {
    $reservation = seedDirtyReservation();

    $this->artisan('data:normalize', ['--dry-run' => true, '--table' => ['reservations']])
        ->assertSuccessful();

    expect($reservation->refresh()->guest_email)->toBe('JEBENNETT@HERSHEYS.COM');
});

it('normalizes existing rows in place and is idempotent', function () {
    $reservation = seedDirtyReservation();

    $this->artisan('data:normalize', ['--force' => true, '--table' => ['reservations']])
        ->assertSuccessful();

    $reservation->refresh();

    expect($reservation->guest_name)->toBe('John Edward Bennett')
        ->and($reservation->guest_email)->toBe('jebennett@hersheys.com')
        ->and($reservation->guest_phone)->toBe('+6281234567890');

    $updatedAt = $reservation->updated_at;

    $this->artisan('data:normalize', ['--force' => true, '--table' => ['reservations']])
        ->assertSuccessful();

    expect($reservation->refresh()->updated_at->toIso8601String())
        ->toBe($updatedAt->toIso8601String());
});

it('only touches the requested targets', function () {
    $reservation = seedDirtyReservation();

    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['email' => 'UPPER@EXAMPLE.COM']);

    $this->artisan('data:normalize', ['--force' => true, '--table' => ['users']])
        ->assertSuccessful();

    expect(User::query()->find($user->id)->email)->toBe('upper@example.com')
        ->and($reservation->refresh()->guest_email)->toBe('JEBENNETT@HERSHEYS.COM');
});

it('normalizes form notification email settings', function () {
    $form = Form::factory()->create();

    DB::table('forms')->where('id', $form->id)->update([
        'settings' => json_encode(['notification_emails' => ['to' => ['Admin@Example.COM'], 'cc' => []]]),
    ]);

    $this->artisan('data:normalize', ['--force' => true, '--table' => ['forms']])
        ->assertSuccessful();

    expect($form->refresh()->settings['notification_emails']['to'])->toBe(['admin@example.com']);
});

it('normalizes form response respondent email and typed answers', function () {
    $form = Form::factory()->create();
    $field = CustomField::factory()->forForm($form)->create(['type' => CustomField::TYPE_EMAIL]);
    $response = FormResponse::factory()->create(['form_id' => $form->id]);

    DB::table('form_responses')->where('id', $response->id)->update([
        'respondent_email' => 'UPPER@EXAMPLE.COM',
        'response_data' => json_encode([$field->ulid => 'Answer@Example.COM']),
    ]);

    $this->artisan('data:normalize', ['--force' => true, '--table' => ['form_responses']])
        ->assertSuccessful();

    $response->refresh();

    expect($response->respondent_email)->toBe('upper@example.com')
        ->and($response->response_data[$field->ulid])->toBe('answer@example.com');
});

it('normalizes stored custom field values by field type', function () {
    $field = CustomField::factory()->create(['type' => CustomField::TYPE_EMAIL]);
    $user = User::factory()->create();

    $value = CustomFieldValue::query()->create([
        'custom_field_id' => $field->id,
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->id,
        'value' => ['MiXeD@Example.COM'],
    ]);

    $this->artisan('data:normalize', ['--force' => true, '--table' => ['custom_field_values']])
        ->assertSuccessful();

    expect($value->refresh()->value)->toBe(['mixed@example.com']);
});

it('normalizes project hotel notification emails inside settings', function () {
    $project = Project::factory()->create();

    DB::table('projects')->where('id', $project->id)->update([
        'settings' => json_encode([
            'website_settings' => ['hotels' => ['notification_email' => ['to' => ['Hotels@Example.COM']]]],
        ]),
    ]);

    $this->artisan('data:normalize', ['--force' => true, '--table' => ['projects']])
        ->assertSuccessful();

    expect($project->refresh()->settings['website_settings']['hotels']['notification_email']['to'])
        ->toBe(['hotels@example.com']);
});
