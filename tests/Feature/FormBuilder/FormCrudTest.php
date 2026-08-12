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

it('stores the multi-step layout setting', function () {
    $form = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);

    $this->putJson("/api/forms/{$form->slug}", [
        'settings' => ['layout' => 'multi_step'],
    ])->assertSuccessful();

    expect($form->fresh()->layout())->toBe('multi_step');
});

it('rejects an unknown layout', function () {
    $form = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);

    $this->putJson("/api/forms/{$form->slug}", [
        'settings' => ['layout' => 'carousel'],
    ])->assertUnprocessable();
});

it('reads an absent layout as single page', function () {
    $form = Form::factory()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
        'settings' => ['confirmation_message' => 'Thanks!'],
    ]);

    expect($form->layout())->toBe('single_page');
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

it('merges a partial settings payload instead of replacing the column', function () {
    // The builder splits authoring across an Editor tab (layout) and a Settings
    // tab (messages, notification emails); each saves only the keys it owns.
    $form = Form::factory()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
        'settings' => [
            'layout' => 'single_page',
            'confirmation_message' => 'Thanks!',
            'redirect_url' => 'https://example.com/done',
            'require_email' => true,
            'notification_emails' => [
                'to' => ['a@example.com'],
                'cc' => ['cc@example.com'],
            ],
        ],
    ]);

    $this->putJson("/api/forms/{$form->slug}", [
        'settings' => ['layout' => 'multi_step'],
    ])->assertSuccessful();

    $settings = $form->fresh()->settings;

    expect($settings['layout'])->toBe('multi_step')
        ->and($settings['confirmation_message'])->toBe('Thanks!')
        ->and($settings['redirect_url'])->toBe('https://example.com/done')
        ->and($settings['require_email'])->toBeTrue()
        ->and($settings['notification_emails']['to'])->toBe(['a@example.com'])
        ->and($settings['notification_emails']['cc'])->toBe(['cc@example.com']);
});

it('merges notification email lists one level deep', function () {
    $form = Form::factory()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
        'settings' => [
            'notification_emails' => [
                'to' => ['a@example.com'],
                'bcc' => ['bcc@example.com'],
            ],
        ],
    ]);

    $this->putJson("/api/forms/{$form->slug}", [
        'settings' => ['notification_emails' => ['to' => ['b@example.com']]],
    ])->assertSuccessful();

    $emails = $form->fresh()->settings['notification_emails'];

    expect($emails['to'])->toBe(['b@example.com'])
        ->and($emails['bcc'])->toBe(['bcc@example.com']);
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

it('bulk restores several trashed forms at once', function () {
    $forms = Form::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    foreach ($forms as $form) {
        $this->deleteJson("/api/forms/{$form->slug}")->assertSuccessful();
    }

    $ids = $forms->pluck('id')->all();

    $this->postJson('/api/forms/trash/restore/bulk', ['ids' => $ids])
        ->assertSuccessful()
        ->assertJsonPath('restored_count', 3);

    expect(Form::whereIn('id', $ids)->count())->toBe(3);
});

it('bulk force deletes several trashed forms at once', function () {
    $forms = Form::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    foreach ($forms as $form) {
        $this->deleteJson("/api/forms/{$form->slug}")->assertSuccessful();
    }

    $ids = $forms->pluck('id')->all();

    $this->deleteJson('/api/forms/trash/bulk', ['ids' => $ids])
        ->assertSuccessful()
        ->assertJsonPath('deleted_count', 2);

    expect(Form::withTrashed()->whereIn('id', $ids)->count())->toBe(0);
});

it('leaves a live form alone when its id is passed to bulk restore', function () {
    $live = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);
    $trashed = Form::factory()->create(['user_id' => $this->user->id, 'created_by' => $this->user->id]);

    $this->deleteJson("/api/forms/{$trashed->slug}")->assertSuccessful();

    // `onlyTrashed()` is what keeps the live one out of the set, so the count
    // has to come back as 1 rather than 2.
    $this->postJson('/api/forms/trash/restore/bulk', ['ids' => [$live->id, $trashed->id]])
        ->assertSuccessful()
        ->assertJsonPath('restored_count', 1);

    expect(Form::find($live->id))->not->toBeNull()
        ->and(Form::find($trashed->id))->not->toBeNull();
});

it('rejects a bulk restore with no ids', function () {
    $this->postJson('/api/forms/trash/restore/bulk', ['ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
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
