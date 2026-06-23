<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Impersonation ("view as user") is intentionally MASTER-ONLY and SILENT:
 * it is gated by a hard role check (never a grantable permission, so an admin
 * can never receive it) and writes NO activity log and sends NO notification.
 */
class UserImpersonationController extends Controller
{
    public function start(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor->hasRole('master'), 403, 'Only master users can impersonate.');
        abort_if($user->id === $actor->id, 403, 'You cannot impersonate yourself.');
        abort_if($user->hasRole('master'), 403, 'You cannot impersonate another master account.');
        abort_if($request->session()->has('impersonator_id'), 409, 'Already impersonating.');

        // Switch the session's user, regenerate the id on this privilege change,
        // then record who started impersonating (regenerate keeps data; re-put to
        // be explicit). Stays silent: no activity log, no notification.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $actor->id);

        return response()->json([
            'message' => 'Impersonation started.',
            'data' => new UserResource($user->load(['roles', 'permissions', 'media'])),
        ]);
    }

    public function leave(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('impersonator_id'), 400, 'Not impersonating.');

        $original = User::find($request->session()->pull('impersonator_id'));

        abort_if($original === null, 410, 'Original account no longer exists.');

        Auth::guard('web')->login($original);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Impersonation ended.',
            'data' => new UserResource($original->load(['roles', 'permissions', 'media'])),
        ]);
    }
}
