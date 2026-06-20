<?php

use App\Models\ApiConsumer;
use App\Models\Attendee;
use App\Models\MagicLink;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_dl']);
    $this->headers = ['X-API-Key' => 'pk_test_dl'];
});

it('mints a magic-link with a valid email token, even for a returning (unclaimed) account', function () {
    // Returning visitor: the account already exists, but the ticket was NOT
    // auto-claimed to them (existing-email tickets never auto-claim). One-click
    // login must still work, keyed on the email the token proves they own.
    $user = User::factory()->create(['email' => 'holder@example.com', 'email_verified_at' => now()]);
    $attendee = Attendee::factory()->create(['email' => 'holder@example.com', 'claimed_by_user_id' => null]);

    $res = $this->postJson("/api/public/attendees/{$attendee->ulid}/dashboard-link", [
        'token' => $attendee->dashboardLoginToken(),
    ], $this->headers);

    $res->assertOk();
    expect($res->json('url'))->toContain('/auth/magic-link/');
    expect($res->json('url'))->toContain('redirect=');
    expect(MagicLink::where('email', 'holder@example.com')->valid()->exists())->toBeTrue();
});

it('sends the holder to /account/tickets after login (safe redirect)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $ml = MagicLink::generate($user->email, 10);

    $this->get("/auth/magic-link/{$ml->token}?redirect=/account/tickets")
        ->assertRedirect(config('app.frontend_url').'/account/tickets');
});

it('rejects an open-redirect and falls back to /dashboard', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $ml = MagicLink::generate($user->email, 10);

    $this->get("/auth/magic-link/{$ml->token}?redirect=//evil.com")
        ->assertRedirect(config('app.frontend_url').'/dashboard');
});

it('rejects the dashboard link without the secret email token (URL bearer)', function () {
    User::factory()->create(['email' => 'holder@example.com', 'email_verified_at' => now()]);
    $attendee = Attendee::factory()->create(['email' => 'holder@example.com']);

    $this->postJson("/api/public/attendees/{$attendee->ulid}/dashboard-link", [], $this->headers)
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'INVALID_LOGIN_TOKEN');

    expect(MagicLink::count())->toBe(0);
});

it('rejects a forged email token', function () {
    User::factory()->create(['email' => 'holder@example.com', 'email_verified_at' => now()]);
    $attendee = Attendee::factory()->create(['email' => 'holder@example.com']);

    $this->postJson("/api/public/attendees/{$attendee->ulid}/dashboard-link", [
        'token' => 'forged-token',
    ], $this->headers)->assertStatus(403);

    expect(MagicLink::count())->toBe(0);
});

it('returns NO_ACCOUNT when a valid token has no matching personal account', function () {
    $attendee = Attendee::factory()->create(['email' => 'noaccount@example.com']);

    $this->postJson("/api/public/attendees/{$attendee->ulid}/dashboard-link", [
        'token' => $attendee->dashboardLoginToken(),
    ], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'NO_ACCOUNT');
});

it('404s for an unknown attendee', function () {
    $this->postJson('/api/public/attendees/NOPE/dashboard-link', [], $this->headers)
        ->assertNotFound();
});

it('exposes has_account on the e-ticket payload', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $claimed = Attendee::factory()->create(['claimed_by_user_id' => $user->id]);
    $anon = Attendee::factory()->create(['claimed_by_user_id' => null]);

    $this->getJson("/api/public/attendees/{$claimed->ulid}", $this->headers)
        ->assertOk()->assertJsonPath('data.has_account', true);
    $this->getJson("/api/public/attendees/{$anon->ulid}", $this->headers)
        ->assertOk()->assertJsonPath('data.has_account', false);
});

it('defaults the e-ticket login button setting to on', function () {
    $attendee = Attendee::factory()->create();

    $this->getJson("/api/public/attendees/{$attendee->ulid}", $this->headers)
        ->assertOk()
        ->assertJsonPath('event.login_button_enabled', true);
});

it('hides the login button on the e-ticket when the event setting is off', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $attendee = Attendee::factory()->create();
    $event = $attendee->ticketOrderItem->ticketOrder->event;

    $this->actingAs($admin)
        ->putJson("/api/events/{$event->id}/ticket-settings", ['login_button_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.login_button_enabled', false);

    $this->getJson("/api/public/attendees/{$attendee->ulid}", $this->headers)
        ->assertOk()
        ->assertJsonPath('event.login_button_enabled', false);
});

it('refuses one-click login for an elevated (staff) account even with a valid token', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $staff = User::factory()->create(['email' => 'staff@example.com', 'email_verified_at' => now()]);
    $staff->assignRole('staff');
    $attendee = Attendee::factory()->create(['email' => 'staff@example.com']);

    $this->postJson("/api/public/attendees/{$attendee->ulid}/dashboard-link", [
        'token' => $attendee->dashboardLoginToken(),
    ], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'NO_ACCOUNT');

    expect(MagicLink::count())->toBe(0);
});

it('does NOT auto-claim a personalize email that already belongs to an account', function () {
    User::factory()->create(['email' => 'taken@example.com', 'email_verified_at' => now()]);
    $attendee = Attendee::factory()->create(['claimed_by_user_id' => null]);

    $this->patchJson("/api/public/attendees/{$attendee->ulid}", [
        'name' => 'Someone', 'email' => 'taken@example.com',
    ], $this->headers)->assertOk();

    $attendee->refresh();
    expect($attendee->claimed_by_user_id)->toBeNull();
    expect($attendee->email)->toBe('taken@example.com');
});

it('auto-claims a brand-new personalize email into a fresh visitor account (1-click stays instant)', function () {
    $attendee = Attendee::factory()->create(['claimed_by_user_id' => null]);

    $this->patchJson("/api/public/attendees/{$attendee->ulid}", [
        'name' => 'New Holder', 'email' => 'fresh@example.com',
    ], $this->headers)->assertOk();

    $attendee->refresh();
    expect($attendee->claimed_by_user_id)->not->toBeNull();
    expect(User::query()->where('email', 'fresh@example.com')->exists())->toBeTrue();
});

it('does not auto-grant staff on a @panoramamedia.co.id magic-link login', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create(['email' => 'someone@panoramamedia.co.id', 'email_verified_at' => null]);
    $user->assignRole('user');
    $ml = MagicLink::generate($user->email, 10);

    $this->get("/auth/magic-link/{$ml->token}");

    expect($user->fresh()->hasRole('staff'))->toBeFalse();
    expect($user->fresh()->hasRole('user'))->toBeTrue();
});
