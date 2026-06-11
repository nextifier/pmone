<?php

use App\Models\Form;
use App\Models\FormField;
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
    $select = FormField::factory()->type('select')->create([
        'form_id' => $this->form->id,
        'label' => 'Ticket',
        'options' => [
            ['value' => 'vip', 'label' => 'VIP'],
            ['value' => 'regular', 'label' => 'Regular'],
        ],
    ]);
    $rating = FormField::factory()->type('rating')->create(['form_id' => $this->form->id, 'label' => 'Rating']);
    $text = FormField::factory()->type('textarea')->create(['form_id' => $this->form->id, 'label' => 'Feedback']);
    FormField::factory()->type('section')->create(['form_id' => $this->form->id, 'label' => 'Section']);

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

it('counts each selection for multi-value fields', function () {
    $multi = FormField::factory()->type('multi_select')->create([
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
    $switch = FormField::factory()->type('switch')->create(['form_id' => $this->form->id]);

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
    $text = FormField::factory()->type('text')->create(['form_id' => $this->form->id]);

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
    $file = FormField::factory()->type('file')->create(['form_id' => $this->form->id]);

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
