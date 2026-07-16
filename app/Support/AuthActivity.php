<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The single definition of which activity-log rows belong to a user's sign-in
 * timeline. The Login History tab shows exactly these rows; the Activity tab
 * shows exactly the complement. Both read this class, so a new sign-in
 * description can never surface on both tabs or vanish from both.
 *
 * Sign-in rows are matched by description, not by log name: the login/logout
 * loggers call activity() without a channel, so those rows carry the default
 * log name. Only RecordFailedLogin uses the 'auth' channel, and it never sets a
 * causer.
 */
final class AuthActivity
{
    /**
     * Descriptions logged verbatim on a successful sign-in or sign-out.
     *
     * @var list<string>
     */
    public const DESCRIPTIONS = [
        'User logged in',
        'User logged out',
        'User logged in via magic link',
    ];

    /**
     * LIKE pattern covering OAuthController's "User logged in via {$provider}".
     */
    public const VIA_PREFIX = 'User logged in via %';

    /**
     * RecordFailedLogin's channel. Those rows are causer-less today; matching it
     * here keeps a causer-scoped feed clean if that ever changes.
     */
    public const LOG_NAME = 'auth';

    /**
     * Constrain to the sign-in rows a user caused (the Login History tab).
     *
     * @param  Builder<covariant \Spatie\Activitylog\Models\Activity>  $query
     * @return Builder<covariant \Spatie\Activitylog\Models\Activity>
     */
    public static function whereCausedLogin(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('description', self::DESCRIPTIONS)
                ->orWhere('description', 'like', self::VIA_PREFIX);
        });
    }

    /**
     * Exclude those same rows (the Activity tab). Exact complement of
     * whereCausedLogin().
     *
     * @param  Builder<covariant \Spatie\Activitylog\Models\Activity>  $query
     * @return Builder<covariant \Spatie\Activitylog\Models\Activity>
     */
    public static function whereNotCausedLogin(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q): void {
                $q->whereNotIn('description', self::DESCRIPTIONS)
                    ->where('description', 'not like', self::VIA_PREFIX);
            })
            // log_name is nullable, and `log_name != 'auth'` evaluates to NULL
            // (not TRUE) for those rows, which would silently drop them.
            ->where(function (Builder $q): void {
                $q->whereNull('log_name')->orWhere('log_name', '!=', self::LOG_NAME);
            });
    }
}
