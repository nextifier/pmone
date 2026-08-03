<?php

use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use App\Support\FormFieldTypes;
use Database\Seeders\ExampleFormsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The deploy pipeline runs `php artisan migrate` but not `db:seed`, so the
 * example forms are refreshed through a migration wrapper. These tests pin the
 * wrapper's contract rather than the seeder's, which ExampleFormsSeederTest
 * already covers.
 */
function runExampleFormsSeedMigration(): void
{
    $migration = require database_path('migrations/2026_08_03_101500_run_example_forms_seed.php');

    $migration->up();
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $this->owner = User::factory()->create(['email_verified_at' => now()]);
    $this->owner->assignRole('master');
});

it('appends field types the showcase is missing without touching existing data', function () {
    $this->seed(ExampleFormsSeeder::class);

    $form = Form::where('slug', 'example-field-showcase')->firstOrFail();

    $staleTypes = ['month', 'month_range', 'year', 'year_range', 'time_range', 'slider_range', 'slider_ruler'];

    $form->fields()->whereIn('type', $staleTypes)->forceDelete();

    $survivingUlids = $form->fields()->pluck('ulid')->sort()->values()->all();
    $answersBefore = FormResponse::where('form_id', $form->id)
        ->orderBy('id')
        ->pluck('response_data', 'id')
        ->all();

    runExampleFormsSeedMigration();

    expect(collect(FormFieldTypes::all())->diff($form->fields()->pluck('type')->unique()))->toBeEmpty()
        ->and($form->fields()->whereIn('ulid', $survivingUlids)->count())->toBe(count($survivingUlids));

    $answersAfter = FormResponse::where('form_id', $form->id)
        ->orderBy('id')
        ->pluck('response_data', 'id')
        ->all();

    expect($answersAfter)->toHaveCount(count($answersBefore));

    foreach ($answersBefore as $responseId => $answers) {
        foreach ($answers as $ulid => $answer) {
            expect($answersAfter[$responseId][$ulid] ?? null)->toEqual($answer);
        }
    }
});

it('is idempotent across repeated runs', function () {
    $this->seed(ExampleFormsSeeder::class);

    $formCount = Form::count();
    $fieldCount = CustomField::count();
    $responseCount = FormResponse::count();

    runExampleFormsSeedMigration();
    runExampleFormsSeedMigration();

    expect(Form::count())->toBe($formCount)
        ->and(CustomField::count())->toBe($fieldCount)
        ->and(FormResponse::count())->toBe($responseCount);
});

it('skips a database with no users instead of throwing', function () {
    User::query()->forceDelete();
    Role::query()->delete();

    expect(fn () => runExampleFormsSeedMigration())->not->toThrow(Exception::class)
        ->and(Form::count())->toBe(0);
});
