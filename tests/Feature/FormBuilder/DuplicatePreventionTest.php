<?php

use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fb_dup_form(string $by): Form
{
    return Form::factory()->published()->create([
        'settings' => [
            'prevent_duplicate' => true,
            'prevent_duplicate_by' => $by,
        ],
    ]);
}

it('blocks duplicate submissions by email', function () {
    $form = fb_dup_form('email');

    $payload = ['responses' => [], 'respondent_email' => 'user@example.com'];

    $this->postJson("/api/public/forms/{$form->slug}/submit", $payload)->assertCreated();
    $this->postJson("/api/public/forms/{$form->slug}/submit", $payload)->assertConflict();
});

it('blocks duplicate submissions by fingerprint', function () {
    $form = fb_dup_form('fingerprint');

    $payload = ['responses' => [], 'browser_fingerprint' => 'fp-abc-123'];

    $this->postJson("/api/public/forms/{$form->slug}/submit", $payload)->assertCreated();
    $this->postJson("/api/public/forms/{$form->slug}/submit", $payload)->assertConflict();
});

it('blocks duplicates when either email or fingerprint matches in both mode', function () {
    $form = fb_dup_form('both');

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        'respondent_email' => 'user@example.com',
        'browser_fingerprint' => 'fp-1',
    ])->assertCreated();

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        'respondent_email' => 'user@example.com',
        'browser_fingerprint' => 'fp-different',
    ])->assertConflict();

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        'respondent_email' => 'other@example.com',
        'browser_fingerprint' => 'fp-1',
    ])->assertConflict();
});

it('allows repeat submissions when prevention is off', function () {
    $form = Form::factory()->published()->create();

    $payload = ['responses' => [], 'respondent_email' => 'user@example.com'];

    $this->postJson("/api/public/forms/{$form->slug}/submit", $payload)->assertCreated();
    $this->postJson("/api/public/forms/{$form->slug}/submit", $payload)->assertCreated();
});

it('reports duplicate status via the check endpoint', function () {
    $form = fb_dup_form('email');

    $this->getJson("/api/public/forms/{$form->slug}/check?email=user@example.com")
        ->assertSuccessful()
        ->assertJson(['already_submitted' => false]);

    $this->postJson("/api/public/forms/{$form->slug}/submit", [
        'responses' => [],
        'respondent_email' => 'user@example.com',
    ])->assertCreated();

    $this->getJson("/api/public/forms/{$form->slug}/check?email=user@example.com")
        ->assertSuccessful()
        ->assertJson(['already_submitted' => true]);
});

it('hides the check endpoint for unpublished forms', function () {
    $form = Form::factory()->create([
        'settings' => ['prevent_duplicate' => true, 'prevent_duplicate_by' => 'email'],
    ]);

    $this->getJson("/api/public/forms/{$form->slug}/check?email=user@example.com")
        ->assertNotFound();
});
