<?php

use App\Models\ApiConsumer;
use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_embed_forms']);
    $this->headers = ['X-API-Key' => 'pk_embed_forms'];
    $this->projectA = Project::factory()->create();
    $this->projectB = Project::factory()->create();
});

function efsHoneypotToken(int $ageSeconds = 10): string
{
    return base64_encode('a1_'.(time() - $ageSeconds).'_b2');
}

it('serves a published project form through the scoped embed route', function () {
    $form = Form::factory()->published()->create(['project_id' => $this->projectA->id]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $form->slug);
});

it('accepts a scoped submission with a valid honeypot and stores it', function () {
    $form = Form::factory()->published()->create(['project_id' => $this->projectA->id]);
    $field = CustomField::factory()->forForm($form)->type('text')->create();

    $this->withHeaders($this->headers)
        ->postJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}/submit", [
            'responses' => [$field->ulid => 'Hello world'],
            'website' => '',
            '_token_time' => efsHoneypotToken(10),
        ])
        ->assertCreated();

    $stored = FormResponse::query()->where('form_id', $form->id)->first();

    expect($stored)->not->toBeNull()
        ->and($stored->response_data[$field->ulid])->toBe('Hello world');
});

it('answers the duplicate check on the scoped route', function () {
    $form = Form::factory()->published()->create(['project_id' => $this->projectA->id]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}/check")
        ->assertOk()
        ->assertJsonPath('already_submitted', false);
});

it('404s when the form belongs to a different project', function () {
    $form = Form::factory()->published()->create(['project_id' => $this->projectB->id]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}")
        ->assertNotFound();
});

it('404s for a project-less form requested through a project route', function () {
    $form = Form::factory()->published()->create(['project_id' => null]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}")
        ->assertNotFound();
});

it('serves a project-less form through the global route', function () {
    $form = Form::factory()->published()->create(['project_id' => null]);

    $this->getJson("/api/public/forms/{$form->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $form->slug);
});

it('rejects a scoped request without an API key', function () {
    $form = Form::factory()->published()->create(['project_id' => $this->projectA->id]);

    $this->getJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}")
        ->assertUnauthorized();
});

it('404s for a draft form on the scoped route', function () {
    $form = Form::factory()->create([
        'project_id' => $this->projectA->id,
        'status' => Form::STATUS_DRAFT,
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}")
        ->assertNotFound();
});

it('403s for a form whose close date has passed', function () {
    $form = Form::factory()->published()->create([
        'project_id' => $this->projectA->id,
        'closes_at' => now()->subHour(),
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$this->projectA->username}/forms/{$form->slug}")
        ->assertForbidden();
});
