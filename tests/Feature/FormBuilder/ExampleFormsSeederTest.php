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

beforeEach(function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $this->owner = User::factory()->create(['email_verified_at' => now()]);
    $this->owner->assignRole('master');
});

it('seeds published example forms with fields and responses', function () {
    $this->seed(ExampleFormsSeeder::class);

    $forms = Form::where('title', 'like', 'Example%')->get();

    expect($forms)->toHaveCount(5);

    foreach ($forms as $form) {
        expect($form->status)->toBe(Form::STATUS_PUBLISHED)
            ->and($form->is_active)->toBeTrue()
            ->and($form->fields()->count())->toBeGreaterThan(0)
            ->and($form->responses()->count())->toBeGreaterThan(0);
    }
});

it('covers every field type across the example forms', function () {
    $this->seed(ExampleFormsSeeder::class);

    $seededTypes = CustomField::where('fieldable_type', Form::class)->whereIn('fieldable_id', Form::pluck('id'))
        ->pluck('type')
        ->unique()
        ->sort()
        ->values()
        ->all();

    $allTypes = collect(FormFieldTypes::all())->sort()->values()->all();

    expect($seededTypes)->toBe($allTypes);
});

it('is idempotent across multiple runs', function () {
    $this->seed(ExampleFormsSeeder::class);

    $formCount = Form::count();
    $fieldCount = CustomField::count();
    $responseCount = FormResponse::count();

    $this->seed(ExampleFormsSeeder::class);

    expect(Form::count())->toBe($formCount)
        ->and(CustomField::count())->toBe($fieldCount)
        ->and(FormResponse::count())->toBe($responseCount);
});

it('seeds responses that respect field types', function () {
    $this->seed(ExampleFormsSeeder::class);

    $form = Form::where('slug', 'example-customer-feedback-survey')->with('fields')->first();
    $rating = $form->fields->firstWhere('type', 'rating');
    $slider = $form->fields->firstWhere('type', 'slider');

    foreach ($form->responses as $response) {
        $ratingValue = $response->response_data[$rating->ulid] ?? null;
        if ($ratingValue !== null) {
            expect($ratingValue)->toBeInt()->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
        }

        $sliderValue = $response->response_data[$slider->ulid] ?? null;
        if ($sliderValue !== null) {
            expect($sliderValue)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }
    }
});

it('seeds showcase answers for the new range types with valid shapes', function () {
    $this->seed(ExampleFormsSeeder::class);

    $form = Form::where('slug', 'example-field-showcase')->with('fields')->first();
    $month = $form->fields->firstWhere('type', 'month');
    $sliderRange = $form->fields->firstWhere('type', 'slider_range');
    $timeRange = $form->fields->firstWhere('type', 'time_range');

    foreach ($form->responses as $response) {
        $monthValue = $response->response_data[$month->ulid] ?? null;
        if ($monthValue !== null) {
            expect($monthValue)->toMatch('/^\d{4}-\d{2}$/');
        }

        $rangeValue = $response->response_data[$sliderRange->ulid] ?? null;
        if ($rangeValue !== null) {
            expect($rangeValue)->toHaveKeys(['start', 'end'])
                ->and($rangeValue['start'])->toBeLessThanOrEqual($rangeValue['end'])
                ->and($rangeValue['start'])->toBeGreaterThanOrEqual(0)
                ->and($rangeValue['end'])->toBeLessThanOrEqual(100);
        }

        $timeValue = $response->response_data[$timeRange->ulid] ?? null;
        if ($timeValue !== null) {
            expect($timeValue['start'])->toMatch('/^\d{2}:\d{2}$/')
                ->and($timeValue['end'])->toMatch('/^\d{2}:\d{2}$/')
                ->and($timeValue['start'] <= $timeValue['end'])->toBeTrue();
        }
    }
});

it('appends missing template fields to existing forms without touching existing data', function () {
    $this->seed(ExampleFormsSeeder::class);

    $form = Form::where('slug', 'example-field-showcase')->first();
    $month = $form->fields()->where('type', 'month')->first();
    $month->delete();

    $keptField = $form->fields()->where('type', 'text')->first();
    $responseCount = $form->responses()->count();
    $sample = $form->responses()->first();
    $sampleAnswer = $sample->response_data[$keptField->ulid] ?? null;

    $this->seed(ExampleFormsSeeder::class);

    $restored = $form->fields()->where('type', 'month')->first();

    expect($restored)->not->toBeNull()
        ->and($restored->ulid)->not->toBe($month->ulid)
        ->and($form->fields()->where('ulid', $keptField->ulid)->exists())->toBeTrue()
        ->and($form->responses()->count())->toBe($responseCount)
        ->and($sample->fresh()->response_data[$keptField->ulid] ?? null)->toBe($sampleAnswer);
});

it('seeds realistic responses without lorem ipsum or example.com emails', function () {
    $this->seed(ExampleFormsSeeder::class);

    $latin = ['ipsum', 'consequuntur', 'voluptas', 'tenetur', 'dolorem', 'asperiores', 'molestias', 'quisquam'];

    FormResponse::all()->each(function (FormResponse $response) use ($latin) {
        foreach ((array) $response->response_data as $value) {
            if (! is_string($value)) {
                continue;
            }

            $haystack = strtolower(strip_tags($value));
            foreach ($latin as $word) {
                expect($haystack)->not->toContain($word);
            }
        }

        if ($response->respondent_email !== null) {
            expect($response->respondent_email)
                ->not->toEndWith('@example.net')
                ->not->toEndWith('@example.org')
                ->not->toEndWith('@example.com');
        }
    });
});

it('uses curated copy for free-text feedback fields', function () {
    $this->seed(ExampleFormsSeeder::class);

    $form = Form::where('slug', 'example-customer-feedback-survey')->with('fields')->first();
    $suggestions = $form->fields->firstWhere('label', 'Suggestions');

    $pool = (new ReflectionClass(ExampleFormsSeeder::class))->getConstant('FREE_TEXT')['Suggestions'];

    foreach ($form->responses as $response) {
        $value = $response->response_data[$suggestions->ulid] ?? null;
        if ($value !== null) {
            expect($pool)->toContain($value);
        }
    }
});

it('skips gracefully when no users exist', function () {
    $this->owner->forceDelete();
    User::query()->forceDelete();

    $this->seed(ExampleFormsSeeder::class);

    expect(Form::count())->toBe(0);
});
