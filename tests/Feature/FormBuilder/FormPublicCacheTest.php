<?php

use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\ResponseCache\Facades\ResponseCache;

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

    $this->form = Form::factory()->published()->create([
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);
});

it('busts the public forms cache when a form is updated', function () {
    $spy = ResponseCache::spy();

    $this->putJson("/api/forms/{$this->form->slug}", ['title' => 'Updated Title'])
        ->assertSuccessful();

    $spy->shouldHaveReceived('clear')->with(['forms-public']);
});

it('busts the public forms cache when a field is created', function () {
    $spy = ResponseCache::spy();

    $this->postJson("/api/forms/{$this->form->slug}/fields", [
        'type' => 'text',
        'label' => 'New Field',
    ])->assertSuccessful();

    $spy->shouldHaveReceived('clear')->with(['forms-public']);
});

it('busts the public forms cache on raw-SQL field reorder', function () {
    $first = FormField::factory()->type('text')->create(['form_id' => $this->form->id]);
    $second = FormField::factory()->type('text')->create(['form_id' => $this->form->id]);

    $spy = ResponseCache::spy();

    $this->putJson("/api/forms/{$this->form->slug}/fields/reorder", [
        'orders' => [
            ['id' => $second->id, 'order' => 1],
            ['id' => $first->id, 'order' => 2],
        ],
    ])->assertSuccessful();

    $spy->shouldHaveReceived('clear')->with(['forms-public']);
});

it('busts the cache on submit only when a response limit is set', function () {
    $limited = Form::factory()->published()->withResponseLimit(10)->create();
    $unlimited = Form::factory()->published()->create();

    $spy = ResponseCache::spy();

    $this->postJson("/api/public/forms/{$unlimited->slug}/submit", ['responses' => []])
        ->assertCreated();
    $spy->shouldNotHaveReceived('clear');

    $this->postJson("/api/public/forms/{$limited->slug}/submit", ['responses' => []])
        ->assertCreated();
    $spy->shouldHaveReceived('clear')->with(['forms-public']);
});

it('serves the public form show from cache until invalidated', function () {
    $originalTitle = $this->form->title;

    $this->getJson("/api/public/forms/{$this->form->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.title', $originalTitle);

    $this->form->updateQuietly(['title' => 'Silently Renamed']);

    $this->getJson("/api/public/forms/{$this->form->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.title', $originalTitle);

    ResponseCache::clear(['forms-public']);

    $this->getJson("/api/public/forms/{$this->form->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Silently Renamed');
});
