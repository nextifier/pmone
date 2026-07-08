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
        'title' => 'Original Form',
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);
});

it('duplicates a form with its fields as a fresh draft', function () {
    $select = CustomField::factory()->type('select')->create([
        'form_id' => $this->form->id,
        'label' => 'Ticket',
    ]);
    $text = CustomField::factory()->type('text')->create([
        'form_id' => $this->form->id,
        'label' => 'Name',
        'validation' => ['required' => true, 'max' => 100],
    ]);
    FormResponse::factory()->count(2)->create(['form_id' => $this->form->id]);

    $response = $this->postJson("/api/forms/{$this->form->slug}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.title', 'Original Form (copy)')
        ->assertJsonPath('data.status', 'draft');

    $copy = Form::where('slug', $response->json('data.slug'))->with('fields')->first();

    expect($copy->slug)->not->toBe($this->form->slug)
        ->and($copy->ulid)->not->toBe($this->form->ulid)
        ->and($copy->responses()->count())->toBe(0)
        ->and($copy->fields)->toHaveCount(2);

    $copiedSelect = $copy->fields->firstWhere('label', 'Ticket');
    $copiedText = $copy->fields->firstWhere('label', 'Name');

    expect($copiedSelect->type)->toBe('select')
        ->and($copiedSelect->options)->toBe($select->options)
        ->and($copiedSelect->ulid)->not->toBe($select->ulid)
        ->and($copiedText->validation)->toBe($text->validation)
        ->and($copiedSelect->order_column)->toBeLessThan($copiedText->order_column);
});

it('denies duplication without the create permission', function () {
    $plainUser = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($plainUser);

    $this->postJson("/api/forms/{$this->form->slug}/duplicate")->assertForbidden();
});
