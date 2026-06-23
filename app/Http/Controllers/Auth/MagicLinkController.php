<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class MagicLinkController extends Controller
{
    public function sendMagicLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');

        // Rate limiting
        $rateLimitKey = 'magic-link:'.$email;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Too many magic link requests. Please try again later.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 900); // 15 minutes

        // Invalidate existing magic links for this email
        MagicLink::forEmail($email)->valid()->update(['used_at' => now()]);

        // Generate new magic link
        $magicLink = MagicLink::generate($email, 15); // 15 minutes expiration

        return $this->sendMagicLinkEmail($email, $magicLink, $request);
    }

    public function loginWithMagicLink(Request $request, string $token): RedirectResponse
    {
        // Atomically consume the token under a row lock so two concurrent
        // requests cannot both pass the validity check before it is marked used.
        $magicLink = DB::transaction(function () use ($token, $request) {
            $ml = MagicLink::query()->where('token', $token)->lockForUpdate()->first();

            if (! $ml || ! $ml->isValid()) {
                return null;
            }

            $ml->markAsUsed($request->ip(), $request->userAgent());

            return $ml;
        });

        if (! $magicLink) {
            return redirect()->to(config('app.frontend_url').'/login')
                ->withErrors(['magic_link' => 'This magic link is invalid, expired, or has already been used.']);
        }

        // Find or create user
        $user = $this->findOrCreateUser($magicLink->email);

        // A soft-deleted account must not be resurrected by logging back in.
        if ($user->trashed()) {
            return redirect()->to(config('app.frontend_url').'/login')
                ->withErrors(['magic_link' => 'This account is no longer active. Please contact support.']);
        }

        // Check if user is active
        if ($user->status === 'inactive') {
            return redirect()->to(config('app.frontend_url').'/login')
                ->withErrors(['magic_link' => 'Account is deactivated. Please contact support.']);
        }

        // Mark user as logged in (the link was already consumed atomically above)
        $user->markAsLoggedIn($request->ip(), $request->userAgent());

        // Verify email if not already verified
        if (! $user->email_verified_at) {
            $user->markEmailAsVerified();
        }

        // Login the user (for web session)
        Auth::login($user);

        // Log activity
        activity()
            ->performedOn($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('User logged in via magic link');

        // Redirect into the frontend. Honor an explicit, same-origin relative
        // ?redirect= path (e.g. the e-ticket "Go to dashboard" sends ticket
        // holders straight to /account/tickets); default to /dashboard.
        return redirect()->to(config('app.frontend_url').$this->safeRedirectPath($request));
    }

    /**
     * Resolve a safe post-login redirect path, rejecting open-redirect attempts
     * (only same-origin relative paths with a single leading slash are allowed).
     */
    private function safeRedirectPath(Request $request): string
    {
        $redirect = (string) $request->query('redirect', '/dashboard');

        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//') || str_contains($redirect, '\\')) {
            return '/dashboard';
        }

        return $redirect;
    }

    private function findOrCreateUser(string $email): User
    {
        $email = strtolower(trim($email));

        $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $user = User::create([
                'name' => explode('@', $email)[0], // Use email prefix as name
                'email' => $email,
                'email_verified_at' => now(),
            ]);

            // Assign the default visitor role. Staff/admin roles are never granted
            // automatically here - they are assigned by an admin (UserController),
            // seeder, or import. Magic-link login must not escalate privileges.
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('user');
            }
        }

        return $user;
    }

    private function sendMagicLinkEmail(string $email, MagicLink $magicLink, Request $request): JsonResponse
    {
        try {
            Mail::to($email)->send(new MagicLinkMail($magicLink));

            // Log activity
            activity()
                ->withProperties([
                    'email' => $email,
                    'ip' => $request->ip(),
                ])
                ->log('Magic link requested');

            return response()->json([
                'message' => 'Magic link sent to your email address.',
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to send magic link email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to send magic link. Please try again.',
            ], 500);
        }
    }
}
