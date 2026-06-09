<?php

use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function fakeSocialiteUser(string $id, string $email, string $name = 'Test User'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->user = [];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('redirects deactivated (soft-deleted) account to login instead of crashing', function () {
    $user = User::factory()->create(['email' => 'deleted@example.com']);
    OAuthProvider::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'g-123',
        'provider_email' => 'deleted@example.com',
        'provider_data' => [],
    ]);
    $user->delete();

    fakeSocialiteUser('g-123', 'deleted@example.com');

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
    $this->assertGuest();
});

it('logs in an existing active user via their oauth provider', function () {
    $user = User::factory()->create(['email' => 'active@example.com']);
    OAuthProvider::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'g-456',
        'provider_email' => 'active@example.com',
        'provider_data' => [],
    ]);

    fakeSocialiteUser('g-456', 'active@example.com');

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/dashboard');
    $this->assertAuthenticatedAs($user);
});
