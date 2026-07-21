<?php

use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['forms.create', 'forms.read', 'forms.update', 'forms.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->form = Form::factory()->published()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);
});

it('returns the full analytics contract', function () {
    $select = CustomField::factory()->type('select')->create([
        'form_id' => $this->form->id,
        'label' => 'Ticket',
        'options' => [
            ['value' => 'vip', 'label' => 'VIP'],
            ['value' => 'regular', 'label' => 'Regular'],
        ],
    ]);
    $rating = CustomField::factory()->type('rating')->create(['form_id' => $this->form->id, 'label' => 'Rating']);
    $text = CustomField::factory()->type('textarea')->create(['form_id' => $this->form->id, 'label' => 'Feedback']);
    CustomField::factory()->type('section')->create(['form_id' => $this->form->id, 'label' => 'Section']);

    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'status' => 'new',
        'submitted_at' => now()->subDays(2),
        'response_data' => [
            $select->ulid => 'vip',
            $rating->ulid => 5,
            $text->ulid => 'Great event',
        ],
    ]);
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'status' => 'read',
        'submitted_at' => now()->subDay(),
        'response_data' => [
            $select->ulid => 'vip',
            $rating->ulid => 3,
        ],
    ]);
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'status' => 'read',
        'submitted_at' => now(),
        'response_data' => [
            $select->ulid => 'regular',
        ],
    ]);

    $response = $this->getJson("/api/forms/{$this->form->slug}/analytics?period=7")
        ->assertSuccessful();

    $data = $response->json('data');

    expect($data['summary']['total_responses'])->toBe(3)
        ->and($data['summary']['today'])->toBe(1)
        ->and($data['summary']['last_7_days'])->toBe(3)
        ->and($data['summary']['status_breakdown'])->toBe([
            'new' => 1, 'read' => 2, 'starred' => 0, 'spam' => 0,
        ]);

    expect(collect($data['responses_per_day'])->sum('count'))->toBe(3)
        ->and(count($data['responses_per_day']))->toBe(8);

    $fields = collect($data['fields']);
    expect($fields)->toHaveCount(3);

    $selectAgg = $fields->firstWhere('ulid', $select->ulid);
    expect($selectAgg['aggregation'])->toBe('options')
        ->and($selectAgg['answered_count'])->toBe(3)
        ->and(collect($selectAgg['options'])->firstWhere('value', 'vip')['count'])->toBe(2)
        ->and(collect($selectAgg['options'])->firstWhere('value', 'vip')['label'])->toBe('VIP')
        ->and(collect($selectAgg['options'])->firstWhere('value', 'regular')['count'])->toBe(1);

    $ratingAgg = $fields->firstWhere('ulid', $rating->ulid);
    expect($ratingAgg['aggregation'])->toBe('numeric')
        ->and($ratingAgg['answered_count'])->toBe(2)
        ->and($ratingAgg['skipped_count'])->toBe(1)
        ->and($ratingAgg['average'])->toEqual(4)
        ->and($ratingAgg['min'])->toEqual(3)
        ->and($ratingAgg['max'])->toEqual(5)
        ->and(collect($ratingAgg['distribution'])->firstWhere('value', 5)['count'])->toBe(1);

    $textAgg = $fields->firstWhere('ulid', $text->ulid);
    expect($textAgg['aggregation'])->toBe('text')
        ->and($textAgg['latest'])->toBe(['Great event']);
});

it('aggregates the new picker and slider types with the expected kinds', function () {
    $year = CustomField::factory()->type('year')->create(['form_id' => $this->form->id, 'label' => 'Year']);
    $ruler = CustomField::factory()->type('slider_ruler')->create(['form_id' => $this->form->id, 'label' => 'Intensity']);
    $monthRange = CustomField::factory()->type('month_range')->create(['form_id' => $this->form->id, 'label' => 'Months']);

    foreach ([
        [$year->ulid => 2020, $ruler->ulid => 10, $monthRange->ulid => ['start' => '2026-03', 'end' => '2026-07']],
        [$year->ulid => 2024, $ruler->ulid => 30],
        [$year->ulid => 2024],
    ] as $data) {
        FormResponse::factory()->create([
            'form_id' => $this->form->id,
            'submitted_at' => now(),
            'response_data' => $data,
        ]);
    }

    $fields = collect($this->getJson("/api/forms/{$this->form->slug}/analytics?period=7")
        ->assertSuccessful()
        ->json('data.fields'));

    $yearAgg = $fields->firstWhere('ulid', $year->ulid);
    expect($yearAgg['aggregation'])->toBe('numeric')
        ->and($yearAgg['min'])->toEqual(2020)
        ->and($yearAgg['max'])->toEqual(2024)
        ->and(collect($yearAgg['distribution'])->firstWhere('value', 2024)['count'])->toBe(2);

    $rulerAgg = $fields->firstWhere('ulid', $ruler->ulid);
    expect($rulerAgg['aggregation'])->toBe('numeric')
        ->and($rulerAgg['average'])->toEqual(20);

    $monthRangeAgg = $fields->firstWhere('ulid', $monthRange->ulid);
    expect($monthRangeAgg['aggregation'])->toBe('text')
        ->and($monthRangeAgg['latest'])->toBe(['2026-03 - 2026-07']);
});

it('respects an explicit start_date/end_date range in responses_per_day', function () {
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [],
        'submitted_at' => now()->subDays(10),
    ]);
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [],
        'submitted_at' => now()->subDays(2),
    ]);

    $start = now()->subDays(4)->toDateString();
    $end = now()->toDateString();

    $data = $this->getJson("/api/forms/{$this->form->slug}/analytics?start_date={$start}&end_date={$end}")
        ->json('data');

    $series = collect($data['responses_per_day']);
    expect($series)->toHaveCount(5)
        ->and($series->first()['date'])->toBe($start)
        ->and($series->last()['date'])->toBe($end)
        ->and($series->sum('count'))->toBe(1);
});

it('rejects an end_date before start_date', function () {
    $start = now()->toDateString();
    $end = now()->subDays(3)->toDateString();

    $this->getJson("/api/forms/{$this->form->slug}/analytics?start_date={$start}&end_date={$end}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

it('counts each selection for multi-value fields', function () {
    $multi = CustomField::factory()->type('multi_select')->create([
        'form_id' => $this->form->id,
        'options' => [
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
        ],
    ]);

    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [$multi->ulid => ['a', 'b']],
    ]);
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [$multi->ulid => ['a']],
    ]);

    $data = $this->getJson("/api/forms/{$this->form->slug}/analytics")->json('data');

    $options = collect($data['fields'][0]['options']);
    expect($options->firstWhere('value', 'a')['count'])->toBe(2)
        ->and($options->firstWhere('value', 'b')['count'])->toBe(1);
});

it('aggregates switch fields as yes/no', function () {
    $switch = CustomField::factory()->type('switch')->create(['form_id' => $this->form->id]);

    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [$switch->ulid => true],
    ]);
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [$switch->ulid => false],
    ]);

    $data = $this->getJson("/api/forms/{$this->form->slug}/analytics")->json('data');

    $options = collect($data['fields'][0]['options']);
    expect($options->firstWhere('value', 'Yes')['count'])->toBe(1)
        ->and($options->firstWhere('value', 'No')['count'])->toBe(1);
});

it('caps text samples at five most recent answers', function () {
    $text = CustomField::factory()->type('text')->create(['form_id' => $this->form->id]);

    foreach (range(1, 7) as $i) {
        FormResponse::factory()->create([
            'form_id' => $this->form->id,
            'response_data' => [$text->ulid => "Answer {$i}"],
        ]);
    }

    $data = $this->getJson("/api/forms/{$this->form->slug}/analytics")->json('data');

    expect($data['fields'][0]['latest'])->toHaveCount(5)
        ->and($data['fields'][0]['latest'][0])->toBe('Answer 7');
});

it('returns empty samples for file fields', function () {
    $file = CustomField::factory()->type('file')->create(['form_id' => $this->form->id]);

    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [$file->ulid => 'form-uploads/1/1/cv.pdf'],
    ]);

    $data = $this->getJson("/api/forms/{$this->form->slug}/analytics")->json('data');

    expect($data['fields'][0]['answered_count'])->toBe(1)
        ->and($data['fields'][0]['latest'])->toBe([]);
});

it('forbids analytics for users who cannot view the form', function () {
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($stranger);

    $this->getJson("/api/forms/{$this->form->slug}/analytics")->assertForbidden();
});
