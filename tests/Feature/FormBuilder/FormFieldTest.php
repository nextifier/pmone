<?php

use App\Models\CustomField;
use App\Models\Form;
use App\Models\User;
use App\Support\FormFieldTypes;
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

    $this->form = Form::factory()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);
});

it('creates a field of every supported type', function (string $type) {
    $payload = [
        'type' => $type,
        'label' => "Field {$type}",
    ];

    if (FormFieldTypes::isChoice($type)) {
        $payload['options'] = [
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
        ];
    }

    $this->postJson("/api/forms/{$this->form->slug}/fields", $payload)->assertSuccessful();

    expect($this->form->fields()->where('type', $type)->exists())->toBeTrue();
})->with(fn () => FormFieldTypes::all());

it('rejects an unknown field type', function () {
    $this->postJson("/api/forms/{$this->form->slug}/fields", [
        'type' => 'hologram',
        'label' => 'Nope',
    ])->assertUnprocessable();
});

it('requires options for choice types', function () {
    $this->postJson("/api/forms/{$this->form->slug}/fields", [
        'type' => 'select',
        'label' => 'Choose',
    ])->assertUnprocessable();
});

it('updates a field', function () {
    $field = CustomField::factory()->type('text')->create(['form_id' => $this->form->id]);

    $this->putJson("/api/forms/{$this->form->slug}/fields/{$field->ulid}", [
        'label' => 'Updated Label',
        'validation' => ['required' => true],
    ])->assertSuccessful();

    $field->refresh();
    expect($field->label)->toBe('Updated Label')
        ->and($field->validation['required'])->toBeTrue();
});

it('deletes a field', function () {
    $field = CustomField::factory()->type('text')->create(['form_id' => $this->form->id]);

    $this->deleteJson("/api/forms/{$this->form->slug}/fields/{$field->ulid}")->assertSuccessful();

    expect(CustomField::find($field->id))->toBeNull();
});

it('persists reordered fields', function () {
    $first = CustomField::factory()->type('text')->create(['form_id' => $this->form->id, 'label' => 'First']);
    $second = CustomField::factory()->type('text')->create(['form_id' => $this->form->id, 'label' => 'Second']);
    $third = CustomField::factory()->type('text')->create(['form_id' => $this->form->id, 'label' => 'Third']);

    $this->putJson("/api/forms/{$this->form->slug}/fields/reorder", [
        'orders' => [
            ['id' => $third->id, 'order' => 1],
            ['id' => $first->id, 'order' => 2],
            ['id' => $second->id, 'order' => 3],
        ],
    ])->assertSuccessful();

    expect($third->fresh()->order_column)->toBe(1)
        ->and($first->fresh()->order_column)->toBe(2)
        ->and($second->fresh()->order_column)->toBe(3);
});

it('stores a url parameter key in field settings', function () {
    $this->postJson("/api/forms/{$this->form->slug}/fields", [
        'type' => 'text',
        'label' => 'Ticket Code',
        'settings' => ['param_key' => 'ticket-code_1'],
    ])->assertSuccessful();

    expect($this->form->fields()->first()->settings['param_key'])->toBe('ticket-code_1');
});

it('rejects invalid url parameter keys', function () {
    $this->postJson("/api/forms/{$this->form->slug}/fields", [
        'type' => 'text',
        'label' => 'Bad Key',
        'settings' => ['param_key' => 'has spaces!'],
    ])->assertUnprocessable();
});

it('rejects reordering fields of another form', function () {
    $otherForm = Form::factory()->create();
    $foreignField = CustomField::factory()->type('text')->create(['form_id' => $otherForm->id]);

    $this->putJson("/api/forms/{$this->form->slug}/fields/reorder", [
        'orders' => [['id' => $foreignField->id, 'order' => 99]],
    ])->assertUnprocessable();

    expect($foreignField->fresh()->order_column)->not->toBe(99);
});
