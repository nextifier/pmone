<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'events.update_branding', 'guard_name' => 'web']);
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create([
        'status' => 'active',
        'username' => 'acme',
        'branding' => [
            'company_name' => 'Acme Events',
            'footer_note' => 'Thank you.',
        ],
    ]);
});

test('show returns project branding', function () {
    $this->getJson('/api/projects/acme/branding')
        ->assertSuccessful()
        ->assertJsonPath('project_id', $this->project->id)
        ->assertJsonPath('branding.company_name', 'Acme Events');
});

test('update requires events.update_branding permission', function () {
    $reader = User::factory()->create(['email_verified_at' => now()]);
    $readerRole = Role::firstOrCreate(['name' => 'reader', 'guard_name' => 'web']);
    $readerRole->syncPermissions([]);
    $reader->assignRole('reader');
    $this->actingAs($reader);

    $this->putJson('/api/projects/acme/branding', [
        'branding' => ['company_name' => 'Hacked'],
    ])->assertStatus(403);
});

test('update stores branding payload', function () {
    $this->putJson('/api/projects/acme/branding', [
        'branding' => [
            'company_name' => 'Panorama Events',
            'tax_id' => '01.234.567.8-999.000',
            'email' => 'finance@panorama.test',
        ],
    ])->assertSuccessful()
        ->assertJsonPath('branding.company_name', 'Panorama Events');

    expect($this->project->fresh()->branding['tax_id'])->toBe('01.234.567.8-999.000');
});

test('update with null branding clears branding and logo media', function () {
    $this->putJson('/api/projects/acme/branding', [
        'branding' => null,
    ])->assertSuccessful()
        ->assertJsonPath('branding', null);

    $project = $this->project->fresh();
    expect($project->branding)->toBeNull();
    expect($project->getFirstMedia('branding_logo'))->toBeNull();
});

test('update moves tmp logo into branding_logo collection', function () {
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
    Storage::disk('local')->put('tmp/uploads/tmp-brandtest/logo.png', $png);
    Storage::disk('local')->put('tmp/uploads/tmp-brandtest/metadata.json', json_encode([
        'original_name' => 'logo.png',
    ]));

    $this->putJson('/api/projects/acme/branding', [
        'branding' => ['company_name' => 'Acme Events'],
        'tmp_logo' => 'tmp-brandtest',
    ])->assertSuccessful();

    $project = $this->project->fresh();
    expect($project->getFirstMedia('branding_logo'))->not->toBeNull();
    expect($project->branding['logo_url'])->not->toBeNull();
    expect(Storage::disk('local')->exists('tmp/uploads/tmp-brandtest'))->toBeFalse();
});

test('update with delete_logo removes existing logo', function () {
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
    Storage::disk('local')->put('tmp/uploads/tmp-brandtest2/logo.png', $png);
    Storage::disk('local')->put('tmp/uploads/tmp-brandtest2/metadata.json', json_encode([
        'original_name' => 'logo.png',
    ]));
    $this->putJson('/api/projects/acme/branding', [
        'branding' => ['company_name' => 'Acme Events'],
        'tmp_logo' => 'tmp-brandtest2',
    ])->assertSuccessful();

    $this->putJson('/api/projects/acme/branding', [
        'branding' => ['company_name' => 'Acme Events'],
        'delete_logo' => true,
    ])->assertSuccessful()
        ->assertJsonPath('branding.logo_url', null);

    expect($this->project->fresh()->getFirstMedia('branding_logo'))->toBeNull();
});
