<?php

use App\Exports\FormResponsesExport;
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

it('excludes section fields from headings', function () {
    FormField::factory()->type('text')->create(['form_id' => $this->form->id, 'label' => 'Name']);
    FormField::factory()->type('section')->create(['form_id' => $this->form->id, 'label' => 'A Section']);

    $export = new FormResponsesExport($this->form->fresh());

    expect($export->headings())->toBe(['Name', 'Email', 'IP Address', 'Submitted At'])
        ->not->toContain('A Section');
});

it('formats values per field type in mapped rows', function () {
    $select = FormField::factory()->type('select')->create([
        'form_id' => $this->form->id,
        'label' => 'Ticket',
        'options' => [
            ['value' => 'vip', 'label' => 'VIP Pass'],
            ['value' => 'regular', 'label' => 'Regular'],
        ],
    ]);
    $multi = FormField::factory()->type('multi_select')->create([
        'form_id' => $this->form->id,
        'label' => 'Sessions',
        'options' => [
            ['value' => 'keynote', 'label' => 'Keynote'],
            ['value' => 'workshop', 'label' => 'Workshop'],
        ],
    ]);
    $range = FormField::factory()->type('date_range')->create(['form_id' => $this->form->id, 'label' => 'Stay']);
    $switch = FormField::factory()->type('switch')->create(['form_id' => $this->form->id, 'label' => 'Newsletter']);
    $rich = FormField::factory()->type('rich_text')->create(['form_id' => $this->form->id, 'label' => 'Letter']);
    $file = FormField::factory()->type('file')->create(['form_id' => $this->form->id, 'label' => 'CV']);

    $response = FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'respondent_email' => 'user@example.com',
        'response_data' => [
            $select->ulid => 'vip',
            $multi->ulid => ['keynote', 'workshop'],
            $range->ulid => ['start' => '2026-07-01', 'end' => '2026-07-03'],
            $switch->ulid => true,
            $rich->ulid => '<p>Hello <strong>there</strong></p>',
            $file->ulid => 'form-uploads/1/2/cv.pdf',
        ],
    ]);

    $row = (new FormResponsesExport($this->form->fresh()))->map($response);

    expect($row[0])->toBe('VIP Pass')
        ->and($row[1])->toBe('Keynote, Workshop')
        ->and($row[2])->toBe('2026-07-01 - 2026-07-03')
        ->and($row[3])->toBe('Yes')
        ->and($row[4])->toBe('Hello there')
        ->and($row[5])->toBe('cv.pdf')
        ->and($row[6])->toBe('user@example.com');
});

it('downloads responses as an xlsx file', function () {
    FormField::factory()->type('text')->create(['form_id' => $this->form->id, 'label' => 'Name']);
    FormResponse::factory()->create(['form_id' => $this->form->id]);

    $this->get("/api/forms/{$this->form->slug}/responses/export")
        ->assertSuccessful()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
});

it('exports only the selected response ids', function () {
    $responses = FormResponse::factory()->count(3)->create(['form_id' => $this->form->id]);

    $export = new FormResponsesExport(
        $this->form->fresh(),
        ['ids' => $responses->take(2)->pluck('id')->all()]
    );

    expect($export->collection())->toHaveCount(2);
});

it('downloads responses as a csv file', function () {
    FormField::factory()->type('text')->create(['form_id' => $this->form->id, 'label' => 'Name']);
    FormResponse::factory()->create(['form_id' => $this->form->id]);

    $response = $this->get("/api/forms/{$this->form->slug}/responses/export?format=csv")
        ->assertSuccessful();

    expect($response->headers->get('content-disposition'))->toContain('.csv');
});

it('rejects an invalid export format', function () {
    $this->getJson("/api/forms/{$this->form->slug}/responses/export?format=pdf")
        ->assertUnprocessable();
});

it('applies the search filter without breaking on sqlite', function () {
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'respondent_email' => 'alice@example.com',
    ]);
    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'respondent_email' => 'bob@example.com',
    ]);

    $export = new FormResponsesExport($this->form->fresh(), ['search' => 'alice']);

    expect($export->collection())->toHaveCount(1);
});
