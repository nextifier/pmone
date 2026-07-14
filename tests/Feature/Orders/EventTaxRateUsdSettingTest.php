<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['events.read', 'events.update'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web'])->syncPermissions(Permission::all());

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'settings' => ['tax_rate' => 11, 'notification_emails' => ['ops@test.com']],
    ]);
});

it('persists tax_rate_usd without wiping other settings keys', function () {
    $this->actingAs($this->staff)->putJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}",
        [
            'settings' => [
                'tax_rate' => 11,
                'tax_rate_usd' => 9,
                'notification_emails' => ['ops@test.com', 'finance@test.com'],
            ],
        ]
    )->assertSuccessful();

    $settings = $this->event->fresh()->settings;

    expect($settings['tax_rate_usd'])->toBe(9);
    expect($settings['tax_rate'])->toBe(11);
    expect($settings['notification_emails'])->toBe(['ops@test.com', 'finance@test.com']);
});
