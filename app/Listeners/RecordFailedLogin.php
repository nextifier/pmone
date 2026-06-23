<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    /**
     * Record a failed password-login attempt into the activity log so it can be
     * surfaced on the user's Login History tab. Uses log_name 'auth' to keep it
     * distinct from the model-change activity stream.
     */
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $user = $event->user instanceof User
            ? $event->user
            : ($email ? User::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email))])->first() : null);

        $logger = activity('auth')
            ->event('login_failed')
            ->withProperties([
                'email' => $email,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

        if ($user) {
            $logger->performedOn($user);
        }

        $logger->log('Failed login attempt');
    }
}
