<?php

use App\Mail\ExhibitorInviteMail;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
});

it('invites an exhibitor with credentials and a magic link when adding a brand with send_login_email', function () {
    Mail::fake();

    $this->actingAs($this->staff)->postJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/brands",
        [
            'brand_name' => 'New Exhibitor Brand',
            'company_name' => 'PT New Exhibitor',
            'emails' => ['pic@example.com'],
            'send_login_email' => true,
        ]
    )->assertStatus(201);

    // A new exhibitor account is created, given the exhibitor role, and attached to the brand.
    $user = User::whereRaw('LOWER(email) = ?', ['pic@example.com'])->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('exhibitor'))->toBeTrue();
    expect($user->brands()->where('brands.name', 'New Exhibitor Brand')->exists())->toBeTrue();

    // The invite email carries the generated password and a working magic-link URL.
    Mail::assertSent(ExhibitorInviteMail::class, function ($mail) {
        $rendered = $mail->render();

        return $mail->hasTo('pic@example.com')
            && $mail->plainPassword !== null
            && str_contains($rendered, $mail->plainPassword)
            && str_contains($rendered, '/auth/magic-link/');
    });

    // The one-time encrypted password is wiped after the email is dispatched.
    expect($user->fresh()->encrypted_password)->toBeNull();
});

it('does not send an invite email when send_login_email is omitted', function () {
    Mail::fake();

    $this->actingAs($this->staff)->postJson(
        "/api/projects/{$this->project->username}/events/{$this->event->slug}/brands",
        [
            'brand_name' => 'Silent Brand',
            'emails' => ['quiet@example.com'],
        ]
    )->assertStatus(201);

    // The exhibitor is still created and attached, just not emailed credentials.
    expect(User::whereRaw('LOWER(email) = ?', ['quiet@example.com'])->exists())->toBeTrue();
    Mail::assertNotSent(ExhibitorInviteMail::class);
});
