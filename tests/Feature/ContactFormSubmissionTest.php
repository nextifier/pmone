<?php

use App\Enums\ContactFormStatus;
use App\Jobs\ProcessContactFormSubmission;
use App\Models\ContactFormSubmission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Mail::fake();

    $this->project = Project::factory()->create([
        'username' => 'testproject',
        'name' => 'Test Project',
        'email' => 'admin@testproject.com',
        'settings' => [
            'contact_form' => [
                'enabled' => true,
                'email_config' => [
                    'to' => ['admin@testproject.com', 'sales@testproject.com'],
                    'cc' => ['manager@testproject.com'],
                    'bcc' => ['archive@testproject.com'],
                    'from_name' => 'Test Project Website',
                    'reply_to' => 'noreply@testproject.com',
                ],
            ],
        ],
    ]);

    $this->user = User::factory()->create();
});

// Submit Contact Form Tests

test('can submit contact form with valid data', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'subject' => 'New Product Inquiry',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
            'message' => 'I am interested in your product.',
        ],
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Your message has been sent successfully. We will get back to you soon.',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'submission_id',
        ]);

    $this->assertDatabaseHas('contact_form_submissions', [
        'project_id' => $this->project->id,
        'subject' => 'New Product Inquiry',
        'status' => ContactFormStatus::New->value,
    ]);

    Queue::assertPushed(ProcessContactFormSubmission::class);
});

test('contact form submission requires project username', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'subject' => 'Test',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['project_username']);
});

test('contact form submission requires form data', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'subject' => 'Test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data']);
});

test('contact form submission validates project exists', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'project_username' => 'nonexistent',
        'subject' => 'Test',
        'data' => [
            'name' => 'John Doe',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['project_username']);
});

test('contact form submission uses default subject when not provided', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ],
    ]);

    $response->assertStatus(201);

    $submission = ContactFormSubmission::latest()->first();
    expect($submission->subject)->toBe('New Contact Form Submission - Test Project');
});

test('contact form submission sanitizes form data', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => '<script>alert("xss")</script>John Doe',
            'email' => 'john@example.com',
        ],
    ]);

    $response->assertStatus(201);

    $submission = ContactFormSubmission::latest()->first();
    expect($submission->form_data['name'])->not->toContain('<script>');
});

test('contact form submission supports dynamic fields', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'brand_name' => 'ACME Corp',
            'product_category' => 'Electronics',
        ],
    ]);

    $response->assertStatus(201);

    $submission = ContactFormSubmission::latest()->first();
    expect($submission->form_data)
        ->toHaveKey('brand_name')
        ->toHaveKey('product_category');
});

test('contact form submission stores IP address and user agent', function () {
    $response = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 Test Browser',
    ])->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ],
    ]);

    $response->assertStatus(201);

    $submission = ContactFormSubmission::latest()->first();
    expect($submission->ip_address)->not->toBeNull();
    expect($submission->user_agent)->toBe('Mozilla/5.0 Test Browser');
});

// Inbox Management Tests (Authenticated)

test('can retrieve list of contact form submissions', function () {
    ContactFormSubmission::factory()->count(5)->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/contact-form-submissions');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'ulid',
                    'subject',
                    'status',
                    'form_data_preview',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
});

test('can filter submissions by status', function () {
    ContactFormSubmission::factory()->count(3)->create([
        'project_id' => $this->project->id,
        'status' => ContactFormStatus::New->value,
    ]);

    ContactFormSubmission::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'status' => ContactFormStatus::Completed->value,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/contact-form-submissions?filter_status=new');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(3);
});

test('can filter submissions by project', function () {
    $anotherProject = Project::factory()->create();

    ContactFormSubmission::factory()->count(3)->create([
        'project_id' => $this->project->id,
    ]);

    ContactFormSubmission::factory()->count(2)->create([
        'project_id' => $anotherProject->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/contact-form-submissions?filter_project={$this->project->id}");

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(3);
});

test('can retrieve single contact form submission detail', function () {
    $submission = ContactFormSubmission::factory()->create([
        'project_id' => $this->project->id,
        'subject' => 'Test Submission',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/contact-form-submissions/{$submission->ulid}");

    $response->assertSuccessful()
        ->assertJson([
            'data' => [
                'ulid' => $submission->ulid,
                'subject' => 'Test Submission',
            ],
        ]);
});

test('can update submission status', function () {
    $submission = ContactFormSubmission::factory()->create([
        'project_id' => $this->project->id,
        'status' => ContactFormStatus::New->value,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/contact-form-submissions/{$submission->ulid}/status", [
            'status' => ContactFormStatus::InProgress->value,
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('contact_form_submissions', [
        'id' => $submission->id,
        'status' => ContactFormStatus::InProgress->value,
    ]);
});

test('can mark submission as followed up', function () {
    $submission = ContactFormSubmission::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/contact-form-submissions/{$submission->ulid}/follow-up");

    $response->assertSuccessful();

    $this->assertDatabaseHas('contact_form_submissions', [
        'id' => $submission->id,
        'followed_up_by' => $this->user->id,
    ]);

    $submission->refresh();
    expect($submission->followed_up_at)->not->toBeNull();
});

test('can delete submission', function () {
    $submission = ContactFormSubmission::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/contact-form-submissions/{$submission->ulid}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('contact_form_submissions', [
        'id' => $submission->id,
    ]);
});

// Unauthorized Access Tests

test('contact form submissions list requires authentication', function () {
    $response = $this->getJson('/api/contact-form-submissions');

    $response->assertStatus(401);
});

test('contact form submission detail requires authentication', function () {
    $submission = ContactFormSubmission::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson("/api/contact-form-submissions/{$submission->ulid}");

    $response->assertStatus(401);
});
