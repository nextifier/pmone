<?php

use App\Models\Form;
use App\Models\User;
use App\Support\FormTemplates;
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
});

it('lists the available templates', function () {
    $response = $this->getJson('/api/form-templates')->assertSuccessful();

    expect($response->json('data'))->toHaveCount(count(FormTemplates::keys()));

    foreach ($response->json('data') as $template) {
        expect($template)->toHaveKeys(['key', 'title', 'description', 'field_count'])
            ->and($template['field_count'])->toBeGreaterThan(0);
    }
});

it('creates a form with fields from a template', function () {
    $template = FormTemplates::get('event-registration');

    $this->postJson('/api/forms', [
        'title' => 'My Registration',
        'template' => 'event-registration',
    ])->assertCreated();

    $form = Form::where('title', 'My Registration')->with('fields')->first();

    expect($form->fields)->toHaveCount(count($template['fields']))
        ->and($form->fields->pluck('type')->all())
        ->toBe(collect($template['fields'])->pluck('type')->all());
});

it('rejects an unknown template key', function () {
    $this->postJson('/api/forms', [
        'title' => 'Bad Template',
        'template' => 'not-a-template',
    ])->assertUnprocessable();
});

it('creates a blank form when no template is given', function () {
    $this->postJson('/api/forms', ['title' => 'Blank Form'])->assertCreated();

    expect(Form::where('title', 'Blank Form')->first()->fields()->count())->toBe(0);
});

it('denies the template list without the create permission', function () {
    $plainUser = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($plainUser);

    $this->getJson('/api/form-templates')->assertForbidden();
});
