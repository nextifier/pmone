<?php

use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a published form with its fields', function () {
    $form = Form::factory()->published()->create();
    $form->fields()->create(['type' => 'text', 'label' => 'Name']);

    $this->getJson("/api/public/forms/{$form->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.title', $form->title)
        ->assertJsonCount(1, 'data.fields');
});

it('returns 404 for a draft form', function () {
    $form = Form::factory()->create();

    $this->getJson("/api/public/forms/{$form->slug}")->assertNotFound();
});

it('returns 404 for an inactive form', function () {
    $form = Form::factory()->published()->create(['is_active' => false]);

    $this->getJson("/api/public/forms/{$form->slug}")->assertNotFound();
});

it('returns 403 before the form opens', function () {
    $form = Form::factory()->published()->create(['opens_at' => now()->addDay()]);

    $this->getJson("/api/public/forms/{$form->slug}")->assertForbidden();
});

it('returns 403 after the form closes', function () {
    $form = Form::factory()->published()->create(['closes_at' => now()->subDay()]);

    $this->getJson("/api/public/forms/{$form->slug}")->assertForbidden();
});

it('returns 403 when the response limit is reached', function () {
    $form = Form::factory()->published()->withResponseLimit(1)->create();
    FormResponse::factory()->create(['form_id' => $form->id]);

    $this->getJson("/api/public/forms/{$form->slug}")->assertForbidden();
});

it('returns the custom closed message when the form is closed', function () {
    $form = Form::factory()->published()->create([
        'closes_at' => now()->subDay(),
        'settings' => ['closed_message' => 'Registration has ended. See you next year!'],
    ]);

    $this->getJson("/api/public/forms/{$form->slug}")
        ->assertForbidden()
        ->assertJson(['message' => 'Registration has ended. See you next year!']);
});

it('returns the custom closed message when the response limit is reached', function () {
    $form = Form::factory()->published()->withResponseLimit(1)->create([
        'settings' => ['closed_message' => 'All slots are taken.'],
    ]);
    FormResponse::factory()->create(['form_id' => $form->id]);

    $this->getJson("/api/public/forms/{$form->slug}")
        ->assertForbidden()
        ->assertJson(['message' => 'All slots are taken.']);
});

it('falls back to the default closed message when unset', function () {
    $form = Form::factory()->published()->create(['closes_at' => now()->subDay()]);

    $this->getJson("/api/public/forms/{$form->slug}")
        ->assertForbidden()
        ->assertJson(['message' => 'Form is closed']);
});

it('does not expose notification emails publicly', function () {
    $form = Form::factory()->published()->create([
        'settings' => [
            'confirmation_message' => 'Thanks!',
            'notification_emails' => ['secret@example.com'],
        ],
    ]);

    $response = $this->getJson("/api/public/forms/{$form->slug}")->assertSuccessful();

    expect(json_encode($response->json()))->not->toContain('secret@example.com');
});
