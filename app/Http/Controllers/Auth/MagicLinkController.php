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
        $magicLink = MagicLink::findByToken($token);

        if (! $magicLink) {
            return redirect()->to(config('app.frontend_url').'/login')
                ->withErrors(['magic_link' => 'Invalid magic link.']);
        }

        if (! $magicLink->isValid()) {
            return redirect()->to(config('app.frontend_url').'/login')
                ->withErrors(['magic_link' => 'Magic link has expired or already been used.']);
        }

        // Find or create user
        $user = $this->findOrCreateUser($magicLink->email);

        // Check if user is active
        if ($user->status === 'inactive') {
            return redirect()->to(config('app.frontend_url').'/login')
                ->withErrors(['magic_link' => 'Account is deactivated. Please contact support.']);
        }

        // Mark magic link as used
        $magicLink->markAsUsed($request->ip(), $request->userAgent());

        // Mark user as logged in
        $user->markAsLoggedIn($request->ip());

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

        // Redirect to frontend dashboard
        return redirect()->to(config('app.frontend_url').'/dashboard');
    }

    private function findOrCreateUser(string $email): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => explode('@', $email)[0], // Use email prefix as name
                'email' => $email,
                'email_verified_at' => now(),
            ]);

            // Assign default role if method exists
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
