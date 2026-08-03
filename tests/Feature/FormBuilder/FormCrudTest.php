<?php

use App\Models\Form;
use App\Models\Project;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('creates a form with settings', function () {
    $response = $this->postJson('/api/forms', [
        'title' => 'My Test Form',
        'description' => 'A test form',
        'status' => 'draft',
        'settings' => [
            'confirmation_message' => 'Thanks!',
            'require_email' => true,
            'prevent_duplicate' => true,
            'prevent_duplicate_by' => 'email',
            'notification_emails' => ['to' => ['admin@example.com']],
        ],
    ]);

    $response->assertSuccessful();

    $form = Form::where('title', 'My Test Form')->first();
    expect($form)->not->toBeNull()
        ->and($form->settings['notification_emails']['to'])->toBe(['admin@example.com'])
        ->and($form->settings['require_email'])->toBeTrue();
});

it('rejects invalid notification emails', function () {
    $this->postJson('/api/forms', [
        'title' => 'Bad Emails',
        'settings' => ['notification_emails' => ['to' => ['not-an-email']]],
    ])->assertUnprocessable();
});

it('rejects more than 20 notification emails per list', function () {
    $emails = collect(range(1, 21))->map(fn ($i) => "user{$i}@example.com")->all();

    $this->postJson('/api/forms', [
        'title' => 'Too Many Emails',
        'settings' => ['notification_emails' => ['to' => $emails]],
    ])->assertUnprocessable();
});

it('accepts to/cc/bcc notification emails', function () {
    $this->postJson('/api/forms', [
        'title' => 'CC BCC Form',
        'settings' => ['notification_emails' => [
            'to' => ['to@example.com'],
            'cc' => ['cc@example.com'],
            'bcc' => ['bcc@example.com'],
        ]],
    ])->assertSuccessful();

    $form = Form::where('title', 'CC BCC Form')->first();
    expect($form->settings['notification_emails']['cc'])->toBe(['cc@example.com'])
        ->and($form->settings['notification_emails']['bcc'])->toBe(['bcc@example.com']);
});

it('denies form creation without permission', function () {
    $plainUser = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($plainUser);

    $this->postJson('/api/forms', ['title' => 'Nope'])->assertForbidden();
});

it('shows a form with fields', function () {
    $form = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);
    $form->fields()->create(['type' => 'text', 'label' => 'Name']);

    $this->getJson("/api/forms/{$form->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.title', $form->title)
        ->assertJsonCount(1, 'data.fields');
});

it('exposes the project website url so the dashboard can link the event copy', function () {
    $project = Project::factory()->create();
    $project->links()->create(['label' => 'Website', 'url' => 'https://iicc.askindo.id']);

    $form = Form::factory()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
        'project_id' => $project->id,
    ]);

    $this->getJson('/api/forms')
        ->assertSuccessful()
        ->assertJsonPath('data.0.project.website_url', 'https://iicc.askindo.id');

    $this->getJson("/api/forms/{$form->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.project.website_url', 'https://iicc.askindo.id');
});

it('resolves every project website url without a query per row', function () {
    foreach (range(1, 5) as $i) {
        $project = Project::factory()->create();
        $project->links()->create(['label' => 'Website', 'url' => "https://event-{$i}.test"]);

        Form::factory()->create([
            'user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'project_id' => $project->id,
        ]);
    }

    // websiteUrl() walks the project's links, so a missing eager load turns the
    // listing into one extra query per form. Counting is the only way that
    // regression shows up: the response body looks identical either way.
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->getJson('/api/forms')->assertSuccessful()->assertJsonCount(5, 'data');

    expect($queries)->toBeLessThan(15);
});

it('leaves the project website url null when no Website link is configured', function () {
    $project = Project::factory()->create();
    $project->links()->create(['label' => 'Instagram', 'url' => 'https://instagram.com/askindo']);

    $form = Form::factory()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
        'project_id' => $project->id,
    ]);

    $this->getJson("/api/forms/{$form->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.project.website_url', null);
});

it('updates settings without losing notification emails', function () {
    $form = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);

    $this->putJson("/api/forms/{$form->slug}", [
        'settings' => [
            'confirmation_message' => 'Updated!',
            'notification_emails' => ['to' => ['a@example.com', 'b@example.com']],
        ],
    ])->assertSuccessful();

    expect($form->fresh()->settings['notification_emails']['to'])->toBe(['a@example.com', 'b@example.com']);
});

it('creates a short link when a form is published', function () {
    $form = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);

    $this->putJson("/api/forms/{$form->slug}", ['status' => 'published'])->assertSuccessful();

    expect(ShortLink::where('destination_url', 'like', '%/f/'.$form->slug)->exists())->toBeTrue();
});

it('exposes the short link for published forms', function () {
    $form = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);

    $this->getJson("/api/forms/{$form->slug}")
        ->assertSuccessful()
        ->assertJsonMissingPath('data.short_link');

    $this->putJson("/api/forms/{$form->slug}", ['status' => 'published'])->assertSuccessful();

    $response = $this->getJson("/api/forms/{$form->slug}")->assertSuccessful();

    expect($response->json('data.short_link.slug'))->not->toBeNull()
        ->and($response->json('data.short_link.url'))->toContain($response->json('data.short_link.slug'));
});

it('soft deletes, lists in trash, restores, and force deletes a form', function () {
    $form = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);

    $this->deleteJson("/api/forms/{$form->slug}")->assertSuccessful();
    expect(Form::withTrashed()->find($form->id)->trashed())->toBeTrue();

    $this->getJson('/api/forms/trash')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $form->id]);

    $this->postJson("/api/forms/trash/{$form->id}/restore")->assertSuccessful();
    expect(Form::find($form->id))->not->toBeNull();

    $this->deleteJson("/api/forms/{$form->slug}")->assertSuccessful();
    $this->deleteJson("/api/forms/trash/{$form->id}")->assertSuccessful();
    expect(Form::withTrashed()->find($form->id))->toBeNull();
});
