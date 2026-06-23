<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuspendUserRequest;
use App\Http\Resources\SessionResource;
use App\Http\Resources\TokenResource;
use App\Models\User;
use App\Support\UserAgentParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserSecurityController extends Controller
{
    /**
     * List the user's active database sessions (device, IP, last activity).
     */
    public function sessions(Request $request, User $user): JsonResponse
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get();

        return response()->json([
            'data' => SessionResource::collection($sessions),
        ]);
    }

    /**
     * Revoke a single session. Scoped by user_id so an admin cannot kill another
     * user's session by guessing the session id.
     */
    public function revokeSession(Request $request, User $user, string $sessionId): JsonResponse
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('session_revoked')
            ->log("Revoked a session for {$user->name}");

        return response()->json(['message' => 'Session revoked.']);
    }

    /**
     * Sign the user out everywhere: delete every session row and revoke API
     * tokens. When the target is the acting admin, their current session (and
     * token) is preserved so they are not logged out of the dashboard.
     */
    public function clearAllSessions(Request $request, User $user): JsonResponse
    {
        $deleted = $this->purgeSessions($request, $user);
        $this->purgeTokens($request, $user);

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('sessions_cleared')
            ->log("Signed out {$user->name} everywhere");

        return response()->json([
            'message' => 'Signed out everywhere.',
            'revoked_sessions' => $deleted,
        ]);
    }

    /**
     * List the user's Sanctum personal access tokens.
     */
    public function tokens(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'data' => TokenResource::collection($user->tokens()->latest()->get()),
        ]);
    }

    /**
     * Revoke a single API token (scoped to the user via the tokens relation).
     */
    public function revokeToken(Request $request, User $user, string $tokenId): JsonResponse
    {
        $user->tokens()->whereKey($tokenId)->delete();

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('token_revoked')
            ->log("Revoked an API token for {$user->name}");

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * The user's authentication timeline (logins, logouts, failed attempts).
     */
    public function loginHistory(Request $request, User $user): JsonResponse
    {
        $loginDescriptions = ['User logged in', 'User logged out', 'User logged in via magic link'];

        $activities = Activity::query()
            ->with('causer.media')
            ->where(function ($outer) use ($user, $loginDescriptions) {
                $outer->where(function ($q) use ($user, $loginDescriptions) {
                    $q->where('causer_id', $user->id)
                        ->where('causer_type', User::class)
                        ->where(function ($d) use ($loginDescriptions) {
                            $d->whereIn('description', $loginDescriptions)
                                ->orWhere('description', 'like', 'User logged in via %');
                        });
                })->orWhere(function ($q) use ($user) {
                    $q->where('log_name', 'auth')
                        ->where('event', 'login_failed')
                        ->where('subject_type', User::class)
                        ->where('subject_id', $user->id);
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => collect($activities->items())->map(fn (Activity $a) => LogController::formatActivity($a)),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ],
        ]);
    }

    /**
     * A compact security snapshot for the detail page (2FA, last login, counts).
     */
    public function securityOverview(Request $request, User $user): JsonResponse
    {
        $user->loadMissing('suspendedBy');

        return response()->json([
            'data' => [
                'two_factor_enabled' => ! is_null($user->two_factor_secret),
                'two_factor_confirmed' => ! is_null($user->two_factor_confirmed_at),
                'last_login_at' => $user->last_login_at?->toISOString(),
                'last_login_ip' => $user->last_login_ip,
                'last_login_device' => UserAgentParser::parse($user->last_login_user_agent),
                'last_seen' => $user->last_seen?->toISOString(),
                'is_online' => $user->isOnline(),
                'sessions_count' => DB::table('sessions')->where('user_id', $user->id)->count(),
                'active_tokens_count' => $user->tokens()->count(),
                'status' => $user->status,
                'suspended_at' => $user->suspended_at?->toISOString(),
                'suspension_reason' => $user->suspension_reason,
                'suspended_by' => $user->suspendedBy ? [
                    'id' => $user->suspendedBy->id,
                    'name' => $user->suspendedBy->name,
                ] : null,
                'failed_logins_24h' => Activity::query()
                    ->where('log_name', 'auth')
                    ->where('event', 'login_failed')
                    ->where('subject_type', User::class)
                    ->where('subject_id', $user->id)
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ],
        ]);
    }

    /**
     * Email the user a password reset link.
     */
    public function sendPasswordReset(Request $request, User $user): JsonResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Password reset link sent.'
                : 'Could not send the password reset link.',
            'status' => $status,
        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerification(Request $request, User $user): JsonResponse
    {
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'This user is already verified.'], 422);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    /**
     * Disable / reset the user's two-factor authentication. This is an audited
     * admin action (unlike impersonation).
     */
    public function resetTwoFactor(Request $request, User $user): JsonResponse
    {
        if ($user->hasRole('master') && ! $request->user()->hasRole('master')) {
            return response()->json(['message' => 'Only master users can reset 2FA for other master users.'], 403);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('two_factor_reset')
            ->log("Reset two-factor authentication for {$user->name}");

        return response()->json(['message' => 'Two-factor authentication has been reset.']);
    }

    /**
     * Suspend a user: deactivate, record the reason, and force them out
     * everywhere. The active-status guard on every login path then blocks
     * them from signing back in.
     */
    public function suspend(SuspendUserRequest $request, User $user): JsonResponse
    {
        if ($user->hasRole('master') && ! $request->user()->hasRole('master')) {
            return response()->json(['message' => 'Only master users can suspend other master users.'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot suspend your own account.'], 403);
        }

        $user->forceFill([
            'status' => 'inactive',
            'suspended_at' => now(),
            'suspension_reason' => $request->validated()['reason'],
            'suspended_by' => $request->user()->id,
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('suspended')
            ->withProperties(['reason' => $request->validated()['reason']])
            ->log("Suspended {$user->name}");

        return response()->json(['message' => 'User suspended.']);
    }

    /**
     * Reactivate a suspended user.
     */
    public function unsuspend(Request $request, User $user): JsonResponse
    {
        $user->forceFill([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
        ])->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('unsuspended')
            ->log("Reactivated {$user->name}");

        return response()->json(['message' => 'User reactivated.']);
    }

    /**
     * Aggregate metrics for the Users list header.
     */
    public function stats(Request $request): JsonResponse
    {
        // Cached briefly: these are header metrics, a ~minute of staleness is fine
        // and avoids ~7 COUNT queries per request once the user table is large.
        $data = Cache::remember('user-security-stats', 60, function () {
            $total = User::query()->count();
            $verified = User::query()->whereNotNull('email_verified_at')->count();

            // Spatie's Role::users() relation is guard-dependent and cannot be used
            // with withCount(), so count through the pivot directly.
            $roleCounts = DB::table('model_has_roles')
                ->where('model_type', (new User)->getMorphClass())
                ->select('role_id', DB::raw('count(*) as aggregate'))
                ->groupBy('role_id')
                ->pluck('aggregate', 'role_id');

            $perRole = Role::query()
                ->get()
                ->map(fn (Role $role) => [
                    'name' => $role->name,
                    'count' => (int) ($roleCounts[$role->id] ?? 0),
                ])
                ->sortByDesc('count')
                ->values();

            return [
                'total' => $total,
                'online_now' => User::query()->where('last_seen', '>', now()->subMinutes(5))->count(),
                'verified' => $verified,
                'verified_percent' => $total > 0 ? (int) round($verified / $total * 100) : 0,
                'new_this_week' => User::query()->where('created_at', '>=', now()->subWeek())->count(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
                'per_role' => $perRole,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Delete the user's sessions, preserving the acting admin's own current
     * session when they target themselves.
     */
    protected function purgeSessions(Request $request, User $user): int
    {
        $query = DB::table('sessions')->where('user_id', $user->id);

        if ($user->id === $request->user()->id && $request->hasSession()) {
            $query->where('id', '!=', $request->session()->getId());
        }

        return $query->delete();
    }

    /**
     * Delete the user's API tokens, preserving the acting admin's current token
     * when they target themselves (no-op for cookie/SPA sessions).
     */
    protected function purgeTokens(Request $request, User $user): void
    {
        $query = $user->tokens();

        if ($user->id === $request->user()->id) {
            $current = $request->user()->currentAccessToken();

            // Only a real stored token has a key to preserve; a cookie/SPA
            // session yields a TransientToken which is unaffected by deletion.
            if ($current instanceof PersonalAccessToken) {
                $query->whereKeyNot($current->getKey());
            }
        }

        $query->delete();
    }
}
