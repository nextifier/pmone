<?php

use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not resurrect a soft-deleted account on magic-link login', function () {
    $user = User::factory()->create(['email' => 'gone@example.com']);
    $user->delete();

    $magicLink = MagicLink::generate('gone@example.com');

    $response = $this->get('/auth/magic-link/'.$magicLink->token);

    $response->assertRedirect(config('app.frontend_url').'/login');
    $this->assertGuest();
    expect($user->fresh()->trashed())->toBeTrue()
        ->and(User::where('email', 'gone@example.com')->count())->toBe(0);
});

it('logs in an active account by email without hitting the unique constraint', function () {
    User::factory()->create(['email' => 'live@example.com']);

    $magicLink = MagicLink::generate('live@example.com');

    $this->get('/auth/magic-link/'.$magicLink->token);

    $this->assertAuthenticated();
    expect(User::where('email', 'live@example.com')->count())->toBe(1);
});
