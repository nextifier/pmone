<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        if (! $this->isProviderSupported($provider)) {
            return $this->redirectToLoginWithError('Unsupported OAuth provider');
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        try {
            if (! $this->isProviderSupported($provider)) {
                logger()->error('Unsupported OAuth provider', ['provider' => $provider]);

                return $this->redirectToLoginWithError('Unsupported OAuth provider');
            }

            $socialiteUser = Socialite::driver($provider)->user();
            $this->logOAuthUserData($provider, $socialiteUser);

            $user = $this->findOrCreateUser($socialiteUser, $provider);

            $this->ensureEmailVerified($user);
            $this->markUserAsLoggedIn($user);
            $this->logUserActivity($user, $provider);

            // Login the user
            Auth::login($user);
            logger()->info('OAuth user logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => $provider,
            ]);

            return redirect()->to(config('app.frontend_url').'/dashboard');

        } catch (\Exception $e) {
            logger()->error('OAuth authentication failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->redirectToLoginWithError('OAuth authentication failed');
        }
    }

    private function isProviderSupported(string $provider): bool
    {
        return in_array($provider, self::SUPPORTED_PROVIDERS);
    }

    private function redirectToLoginWithError(string $message): RedirectResponse
    {
        return redirect()->to(config('app.frontend_url').'/login')
            ->withErrors(['oauth' => $message]);
    }

    private function logOAuthUserData(string $provider, $socialiteUser): void
    {
        logger()->info('OAuth user data received', [
            'provider' => $provider,
            'email' => $socialiteUser->getEmail(),
            'id' => $socialiteUser->getId(),
            'name' => $socialiteUser->getName(),
        ]);
    }

    private function findOrCreateUser($socialiteUser, string $provider): User
    {
        // Check if OAuth provider exists
        $oauthProvider = OAuthProvider::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($oauthProvider) {
            // Update existing OAuth provider data
            $this->updateOAuthProvider($oauthProvider, $socialiteUser);

            return $oauthProvider->user;
        }

        // Find or create user by email
        $user = User::where('email', $socialiteUser->getEmail())->first();

        if (! $user) {
            $user = $this->createNewUser($socialiteUser);
        }

        // Create OAuth provider record
        $this->createOAuthProvider($user, $socialiteUser, $provider);

        return $user;
    }

    private function updateOAuthProvider(OAuthProvider $oauthProvider, $socialiteUser): void
    {
        $oauthProvider->update([
            'provider_email' => $socialiteUser->getEmail(),
            'provider_data' => $this->buildProviderData($socialiteUser),
        ]);
    }

    private function createNewUser($socialiteUser): User
    {
        $user = User::create([
            'name' => $socialiteUser->getName(),
            'email' => $socialiteUser->getEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)),
        ]);

        // Assign default role if method exists
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('user');
        }

        // Save avatar if available
        if ($socialiteUser->getAvatar()) {
            $this->saveUserAvatar($user, $socialiteUser->getAvatar());
        }

        return $user;
    }

    private function createOAuthProvider(User $user, $socialiteUser, string $provider): void
    {
        $user->oauthProviders()->create([
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
            'provider_email' => $socialiteUser->getEmail(),
            'provider_data' => $this->buildProviderData($socialiteUser),
        ]);
    }

    private function buildProviderData($socialiteUser): array
    {
        return [
            'name' => $socialiteUser->getName(),
            'avatar' => $socialiteUser->getAvatar(),
            'raw' => $socialiteUser->user ?? [],
        ];
    }

    private function ensureEmailVerified(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    }

    private function markUserAsLoggedIn(User $user): void
    {
        $user->markAsLoggedIn(request()->ip());
    }

    private function logUserActivity(User $user, string $provider): void
    {
        if (function_exists('activity')) {
            activity()
                ->performedOn($user)
                ->withProperties([
                    'provider' => $provider,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log("User logged in via {$provider}");
        }
    }

    private function saveUserAvatar(User $user, string $avatarUrl): void
    {
        try {
            // Check if media library is available
            if (method_exists($user, 'addMediaFromUrl')) {
                $user->addMediaFromUrl($avatarUrl)
                    ->toMediaCollection('profile_image');
            }
        } catch (\Exception $e) {
            // Log error but don't fail the authentication
            logger()->error('Failed to save OAuth avatar', [
                'user_id' => $user->id,
                'avatar_url' => $avatarUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
