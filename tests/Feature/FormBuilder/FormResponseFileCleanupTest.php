<?php

use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

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

function fb_seed_response_file(Form $form, FormResponse $response, string $name = 'cv.pdf'): string
{
    $path = "form-uploads/{$form->id}/{$response->id}/{$name}";
    Storage::disk('local')->put($path, 'content');

    return $path;
}

it('deletes uploaded files when a response is deleted', function () {
    $response = FormResponse::factory()->create(['form_id' => $this->form->id]);
    $sibling = FormResponse::factory()->create(['form_id' => $this->form->id]);

    $path = fb_seed_response_file($this->form, $response);
    $siblingPath = fb_seed_response_file($this->form, $sibling);

    $this->deleteJson("/api/forms/{$this->form->slug}/responses/{$response->ulid}")
        ->assertSuccessful();

    Storage::disk('local')->assertMissing($path);
    Storage::disk('local')->assertExists($siblingPath);
});

it('deletes uploaded files on bulk destroy', function () {
    $responses = FormResponse::factory()->count(3)->create(['form_id' => $this->form->id]);
    $paths = $responses->map(fn ($r) => fb_seed_response_file($this->form, $r));

    $this->deleteJson("/api/forms/{$this->form->slug}/responses/bulk", [
        'ids' => $responses->take(2)->pluck('id')->all(),
    ])->assertSuccessful();

    Storage::disk('local')->assertMissing($paths[0]);
    Storage::disk('local')->assertMissing($paths[1]);
    Storage::disk('local')->assertExists($paths[2]);
    expect($this->form->responses()->count())->toBe(1);
});

it('deletes the whole upload directory when a form is force deleted', function () {
    $response = FormResponse::factory()->create(['form_id' => $this->form->id]);
    $path = fb_seed_response_file($this->form, $response);

    $this->deleteJson("/api/forms/{$this->form->slug}")->assertSuccessful();
    $this->deleteJson("/api/forms/trash/{$this->form->id}")->assertSuccessful();

    Storage::disk('local')->assertMissing($path);
});

it('keeps uploaded files when a form is only soft deleted', function () {
    $response = FormResponse::factory()->create(['form_id' => $this->form->id]);
    $path = fb_seed_response_file($this->form, $response);

    $this->deleteJson("/api/forms/{$this->form->slug}")->assertSuccessful();

    Storage::disk('local')->assertExists($path);
});
