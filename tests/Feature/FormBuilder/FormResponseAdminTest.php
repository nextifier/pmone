<?php

use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

it('lists responses with pagination', function () {
    FormResponse::factory()->count(20)->create(['form_id' => $this->form->id]);

    $this->getJson("/api/forms/{$this->form->slug}/responses?per_page=15")
        ->assertSuccessful()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 20);
});

it('filters responses by status', function () {
    FormResponse::factory()->count(3)->create(['form_id' => $this->form->id, 'status' => 'new']);
    FormResponse::factory()->count(2)->create(['form_id' => $this->form->id, 'status' => 'spam']);

    $this->getJson("/api/forms/{$this->form->slug}/responses?filter_status=spam")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/forms/{$this->form->slug}/responses?filter_status=new,spam")
        ->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

it('searches responses by respondent email', function () {
    FormResponse::factory()->create(['form_id' => $this->form->id, 'respondent_email' => 'alice@example.com']);
    FormResponse::factory()->create(['form_id' => $this->form->id, 'respondent_email' => 'bob@example.com']);

    $this->getJson("/api/forms/{$this->form->slug}/responses?filter_search=alice")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('searches answer contents only on postgres without erroring on sqlite', function () {
    $field = CustomField::factory()->type('text')->create(['form_id' => $this->form->id]);

    FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'respondent_email' => 'someone@example.com',
        'response_data' => [$field->ulid => 'unique-answer-keyword'],
    ]);

    $this->getJson("/api/forms/{$this->form->slug}/responses?filter_search=unique-answer-keyword")
        ->assertSuccessful();
});

it('bulk updates response status', function () {
    $responses = FormResponse::factory()->count(3)->create(['form_id' => $this->form->id, 'status' => 'new']);

    $this->putJson("/api/forms/{$this->form->slug}/responses/bulk-status", [
        'ids' => $responses->pluck('id')->all(),
        'status' => 'read',
    ])->assertSuccessful();

    expect($this->form->responses()->where('status', 'read')->count())->toBe(3);
});

it('bulk deletes responses', function () {
    $responses = FormResponse::factory()->count(3)->create(['form_id' => $this->form->id]);

    $this->deleteJson("/api/forms/{$this->form->slug}/responses/bulk", [
        'ids' => $responses->take(2)->pluck('id')->all(),
    ])->assertSuccessful();

    expect($this->form->responses()->count())->toBe(1);
});

it('deletes a single response', function () {
    $response = FormResponse::factory()->create(['form_id' => $this->form->id]);

    $this->deleteJson("/api/forms/{$this->form->slug}/responses/{$response->ulid}")
        ->assertSuccessful();

    expect(FormResponse::find($response->id))->toBeNull();
});

it('downloads an uploaded response file', function () {
    Storage::fake('local');

    $field = CustomField::factory()->type('file')->create(['form_id' => $this->form->id]);
    $path = "form-uploads/{$this->form->id}/1/cv.pdf";
    Storage::disk('local')->put($path, 'fake-pdf-content');

    $response = FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [$field->ulid => $path],
    ]);

    $this->get("/api/forms/{$this->form->slug}/responses/{$response->ulid}/files/{$field->ulid}")
        ->assertSuccessful()
        ->assertDownload('cv.pdf');
});

it('blocks file downloads outside the form upload directory', function () {
    Storage::fake('local');
    Storage::disk('local')->put('secrets/env.txt', 'top-secret');

    $field = CustomField::factory()->type('file')->create(['form_id' => $this->form->id]);
    $response = FormResponse::factory()->create([
        'form_id' => $this->form->id,
        'response_data' => [$field->ulid => 'secrets/env.txt'],
    ]);

    $this->get("/api/forms/{$this->form->slug}/responses/{$response->ulid}/files/{$field->ulid}")
        ->assertNotFound();
});

it('forbids access to responses of forms the user cannot view', function () {
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($stranger);

    $this->getJson("/api/forms/{$this->form->slug}/responses")->assertForbidden();
});
