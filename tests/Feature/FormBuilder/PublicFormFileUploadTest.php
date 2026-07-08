<?php

use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->form = Form::factory()->published()->create();
});

function fb_upload(object $test, Form $form, UploadedFile $file, ?string $fieldUlid = null): TestResponse
{
    $payload = ['file' => $file];
    if ($fieldUlid) {
        $payload['field'] = $fieldUlid;
    }

    return $test->postJson("/api/public/forms/{$form->slug}/upload", $payload);
}

it('uploads a file to temporary storage', function () {
    $response = fb_upload($this, $this->form, UploadedFile::fake()->create('cv.pdf', 100));

    $response->assertSuccessful();
    $folder = $response->json('folder');

    expect($folder)->toStartWith('form-');
    Storage::disk('local')->assertExists("tmp/uploads/{$folder}/cv.pdf");
    Storage::disk('local')->assertExists("tmp/uploads/{$folder}/metadata.json");
});

it('rejects files larger than the field limit', function () {
    $field = CustomField::factory()->type('file')->create([
        'form_id' => $this->form->id,
        'validation' => ['max_file_size' => 100, 'allowed_file_types' => ['pdf']],
    ]);

    fb_upload($this, $this->form, UploadedFile::fake()->create('big.pdf', 500), $field->ulid)
        ->assertUnprocessable();
});

it('rejects disallowed file extensions', function () {
    $field = CustomField::factory()->type('file')->create([
        'form_id' => $this->form->id,
        'validation' => ['max_file_size' => 5120, 'allowed_file_types' => ['pdf']],
    ]);

    fb_upload($this, $this->form, UploadedFile::fake()->create('script.exe', 10), $field->ulid)
        ->assertUnprocessable();
});

it('moves a single uploaded file on submit', function () {
    $field = CustomField::factory()->type('file')->create(['form_id' => $this->form->id]);

    $folder = fb_upload($this, $this->form, UploadedFile::fake()->create('cv.pdf', 100), $field->ulid)
        ->json('folder');

    $this->postJson("/api/public/forms/{$this->form->slug}/submit", [
        'responses' => [$field->ulid => $folder],
    ])->assertCreated();

    $stored = FormResponse::where('form_id', $this->form->id)->first();
    $path = $stored->response_data[$field->ulid];

    expect($path)->toStartWith("form-uploads/{$this->form->id}/");
    Storage::disk('local')->assertExists($path);
    Storage::disk('local')->assertMissing("tmp/uploads/{$folder}/cv.pdf");
});

it('moves multiple uploaded files on submit', function () {
    $field = CustomField::factory()->type('file')->create([
        'form_id' => $this->form->id,
        'settings' => ['multiple' => true],
        'validation' => ['max_files' => 3],
    ]);

    $folderA = fb_upload($this, $this->form, UploadedFile::fake()->create('a.pdf', 50), $field->ulid)->json('folder');
    $folderB = fb_upload($this, $this->form, UploadedFile::fake()->create('b.pdf', 50), $field->ulid)->json('folder');

    $this->postJson("/api/public/forms/{$this->form->slug}/submit", [
        'responses' => [$field->ulid => [$folderA, $folderB]],
    ])->assertCreated();

    $stored = FormResponse::where('form_id', $this->form->id)->first();
    $paths = $stored->response_data[$field->ulid];

    expect($paths)->toBeArray()->toHaveCount(2);
    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
    }
});

it('rejects more folders than max_files allows', function () {
    $field = CustomField::factory()->type('file')->create([
        'form_id' => $this->form->id,
        'settings' => ['multiple' => true],
        'validation' => ['max_files' => 2],
    ]);

    $this->postJson("/api/public/forms/{$this->form->slug}/submit", [
        'responses' => [$field->ulid => ['form-a', 'form-b', 'form-c']],
    ])->assertUnprocessable();
});

it('reverts an uploaded file', function () {
    $folder = fb_upload($this, $this->form, UploadedFile::fake()->create('cv.pdf', 100))->json('folder');

    $this->call(
        'DELETE',
        "/api/public/forms/{$this->form->slug}/upload",
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        $folder
    )->assertSuccessful();

    Storage::disk('local')->assertMissing("tmp/uploads/{$folder}/cv.pdf");
});

it('rejects uploads for a non-file field ulid', function () {
    $field = CustomField::factory()->type('text')->create(['form_id' => $this->form->id]);

    fb_upload($this, $this->form, UploadedFile::fake()->create('cv.pdf', 100), $field->ulid)
        ->assertNotFound();
});
