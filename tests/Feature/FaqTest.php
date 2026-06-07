<?php

use App\Models\Event;
use App\Models\Faq;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'faqs.create', 'faqs.read', 'faqs.update',
        'faqs.delete', 'faqs.restore',
        'events.read', 'events.update',
        'projects.read',
    ];

    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/faqs";
});

it('lists faqs scoped to the event ordered by order_column', function () {
    Faq::factory()->count(3)->create(['event_id' => $this->event->id]);

    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    Faq::factory()->count(2)->create(['event_id' => $otherEvent->id]);

    $response = $this->getJson($this->apiBase);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('creates a faq with translatable question and answer', function () {
    $payload = [
        'question' => ['en' => 'When is the event?', 'id' => 'Kapan acaranya?'],
        'answer' => ['en' => '<p>On {{event_date}}.</p>', 'id' => '<p>Pada {{event_date}}.</p>'],
        'is_active' => true,
    ];

    $response = $this->postJson($this->apiBase, $payload);

    $response->assertCreated()
        ->assertJsonPath('data.question.en', 'When is the event?')
        ->assertJsonPath('data.answer.id', '<p>Pada {{event_date}}.</p>')
        ->assertJsonPath('data.is_active', true);

    $faq = Faq::first();
    expect($faq->event_id)->toBe($this->event->id);
    expect($faq->getTranslation('question', 'id'))->toBe('Kapan acaranya?');
    expect($faq->order_column)->not->toBeNull();
});

it('requires english question and answer', function () {
    $this->postJson($this->apiBase, ['question' => ['id' => 'x']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['question.en', 'answer']);
});

it('updates a faq preserving untouched locales', function () {
    $faq = Faq::factory()->create([
        'event_id' => $this->event->id,
        'question' => ['en' => 'Old?', 'id' => 'Lama?', 'ja' => 'ジャ?'],
    ]);

    $response = $this->putJson("{$this->apiBase}/{$faq->id}", [
        'question' => ['en' => 'New?', 'id' => 'Baru?', 'ja' => 'ジャ?'],
    ]);

    $response->assertSuccessful()->assertJsonPath('data.question.en', 'New?');
    expect($faq->fresh()->getTranslation('question', 'ja'))->toBe('ジャ?');
});

it('soft deletes a faq', function () {
    $faq = Faq::factory()->create(['event_id' => $this->event->id]);

    $this->deleteJson("{$this->apiBase}/{$faq->id}")->assertSuccessful();

    expect(Faq::find($faq->id))->toBeNull();
    expect(Faq::withTrashed()->find($faq->id))->not->toBeNull();
});

it('reorders faqs', function () {
    $a = Faq::factory()->create(['event_id' => $this->event->id]);
    $b = Faq::factory()->create(['event_id' => $this->event->id]);

    $response = $this->postJson("{$this->apiBase}/reorder", [
        'orders' => [
            ['id' => $b->id, 'order' => 1],
            ['id' => $a->id, 'order' => 2],
        ],
    ]);

    $response->assertSuccessful();
    expect($b->fresh()->order_column)->toBe(1);
    expect($a->fresh()->order_column)->toBe(2);
});

it('restores and force deletes from trash', function () {
    $faq = Faq::factory()->create(['event_id' => $this->event->id]);
    $faq->delete();

    $this->getJson("{$this->apiBase}/trash")->assertSuccessful()->assertJsonCount(1, 'data');

    $this->postJson("{$this->apiBase}/trash/{$faq->id}/restore")->assertSuccessful();
    expect(Faq::find($faq->id))->not->toBeNull();

    $faq->delete();
    $this->deleteJson("{$this->apiBase}/trash/{$faq->id}")->assertSuccessful();
    expect(Faq::withTrashed()->find($faq->id))->toBeNull();
});

it('forbids creating a faq without permission', function () {
    $this->user->removeRole('master');
    $this->user->syncPermissions(['events.read']);

    $this->postJson($this->apiBase, [
        'question' => ['en' => 'Nope?'],
        'answer' => ['en' => '<p>No</p>'],
    ])->assertForbidden();
});

it('lists source events (same project, with faq) and copies their faq', function () {
    $source = Event::factory()->create(['project_id' => $this->project->id]);
    Faq::factory()->count(3)->create(['event_id' => $source->id]);

    $this->getJson("{$this->apiBase}/source-events")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $source->id)
        ->assertJsonPath('data.0.faqs_count', 3);

    $this->postJson("{$this->apiBase}/copy-from-event", ['source_event_id' => $source->id])
        ->assertSuccessful()
        ->assertJsonPath('copied_count', 3);

    expect(Faq::where('event_id', $this->event->id)->count())->toBe(3);
});

it('preserves translations and order when copying faq', function () {
    $source = Event::factory()->create(['project_id' => $this->project->id]);
    $a = Faq::factory()->create([
        'event_id' => $source->id,
        'question' => ['en' => 'A', 'id' => 'A-id'],
        'answer' => ['en' => '<p>Held on {{event_date}}</p>', 'id' => 'x'],
    ]);
    $a->order_column = 5;
    $a->save();

    $this->postJson("{$this->apiBase}/copy-from-event", ['source_event_id' => $source->id])
        ->assertSuccessful();

    $copy = Faq::where('event_id', $this->event->id)->first();
    expect($copy->getTranslation('question', 'id'))->toBe('A-id');
    expect($copy->getTranslation('answer', 'en'))->toBe('<p>Held on {{event_date}}</p>');
    expect($copy->order_column)->toBe(5);
});

it('rejects copy from an event outside the project', function () {
    $otherProject = Project::factory()->create();
    $foreign = Event::factory()->create(['project_id' => $otherProject->id]);
    Faq::factory()->create(['event_id' => $foreign->id]);

    $this->postJson("{$this->apiBase}/copy-from-event", ['source_event_id' => $foreign->id])
        ->assertStatus(422);
});
