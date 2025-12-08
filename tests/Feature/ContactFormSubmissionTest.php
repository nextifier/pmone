<?php

use App\Enums\ContactFormStatus;
use App\Jobs\ProcessContactFormSubmission;
use App\Models\ApiConsumer;
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

    // Create API consumer for contact form tests
    $this->apiConsumer = ApiConsumer::factory()->create([
        'name' => 'Test Consumer',
        'api_key' => 'pk_test_api_key_12345',
        'is_active' => true,
        'allowed_origins' => [],
        'rate_limit' => 0, // Unlimited for tests
    ]);
});

// Helper function to generate valid honeypot timestamp token
function generateValidTimestampToken(): string
{
    $timestamp = time() - 5; // 5 seconds ago (passes minimum time check)
    $random1 = bin2hex(random_bytes(4));
    $random2 = bin2hex(random_bytes(4));

    return base64_encode("{$random1}_{$timestamp}_{$random2}");
}

// Helper function to make authenticated API requests with API key and honeypot
function postJsonWithApiKey($test, string $uri, array $data = [])
{
    // Add honeypot fields if not present
    $data['website'] = $data['website'] ?? '';
    $data['_token_time'] = $data['_token_time'] ?? generateValidTimestampToken();

    return $test->withHeaders([
        'X-API-Key' => 'pk_test_api_key_12345',
    ])->postJson($uri, $data);
}

// Submit Contact Form Tests

test('can submit contact form with valid data', function () {
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
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
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
        'subject' => 'Test',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['project_username']);
});

test('contact form submission requires form data', function () {
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'subject' => 'Test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['data']);
});

test('contact form submission validates project exists', function () {
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
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
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
        ],
    ]);

    $response->assertStatus(201);

    $submission = ContactFormSubmission::latest()->first();
    expect($submission->subject)->toBe('New Contact Form Submission - Test Project');
});

test('contact form submission sanitizes form data', function () {
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => '<script>alert("xss")</script>John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
        ],
    ]);

    $response->assertStatus(201);

    $submission = ContactFormSubmission::latest()->first();
    expect($submission->form_data['name'])->not->toContain('<script>');
});

test('contact form submission supports dynamic fields', function () {
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '08123456789',
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
        'X-API-Key' => 'pk_test_api_key_12345',
        'User-Agent' => 'Mozilla/5.0 Test Browser',
    ])->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
        ],
        'website' => '',
        '_token_time' => generateValidTimestampToken(),
    ]);

    $response->assertStatus(201);

    $submission = ContactFormSubmission::latest()->first();
    expect($submission->ip_address)->not->toBeNull();
    expect($submission->user_agent)->toBe('Mozilla/5.0 Test Browser');
});

test('contact form submission requires API key', function () {
    $response = $this->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
        ],
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'API key is required',
        ]);
});

test('contact form submission rejects invalid API key', function () {
    $response = $this->withHeaders([
        'X-API-Key' => 'pk_invalid_key',
    ])->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
        ],
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid or inactive API key',
        ]);
});

// Honeypot Tests

test('contact form submission rejects when honeypot field is filled', function () {
    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_api_key_12345',
    ])->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'phone' => '08123456789',
        ],
        'website' => 'https://spam-site.com', // Bot filled this field
        '_token_time' => generateValidTimestampToken(),
    ]);

    $response->assertStatus(422);
});

test('contact form submission rejects when submitted too quickly', function () {
    // Generate token with current timestamp (too fast)
    $timestamp = time();
    $token = base64_encode("abc_{$timestamp}_xyz");

    $response = $this->withHeaders([
        'X-API-Key' => 'pk_test_api_key_12345',
    ])->postJson('/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'Fast Bot',
            'email' => 'fastbot@example.com',
            'phone' => '08123456789',
        ],
        'website' => '',
        '_token_time' => $token,
    ]);

    $response->assertStatus(422);
});

test('contact form submission accepts valid honeypot data', function () {
    $response = postJsonWithApiKey($this, '/api/contact-forms/submit', [
        'project_username' => 'testproject',
        'data' => [
            'name' => 'Real User',
            'email' => 'real@example.com',
            'phone' => '08123456789',
        ],
        'website' => '', // Empty as expected
        '_token_time' => generateValidTimestampToken(),
    ]);

    $response->assertStatus(201);
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

    // Check that the submission is soft-deleted
    $this->assertSoftDeleted('contact_form_submissions', [
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
