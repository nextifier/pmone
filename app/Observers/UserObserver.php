<?php

namespace App\Observers;

use App\Models\ShortLink;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Create a short link for the user profile
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $profileUrl = rtrim($frontendUrl, '/').'/users/'.$user->username;

        ShortLink::create([
            'user_id' => $user->id,
            'slug' => $user->username,
            'destination_url' => $profileUrl,
            'is_active' => true,
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // If username changed, update the associated short link
        if ($user->isDirty('username')) {
            $oldUsername = $user->getOriginal('username');

            // Find the short link with the old username
            $shortLink = ShortLink::where('user_id', $user->id)
                ->where('slug', $oldUsername)
                ->first();

            if ($shortLink) {
                $frontendUrl = config('app.frontend_url', config('app.url'));
                $profileUrl = rtrim($frontendUrl, '/').'/users/'.$user->username;

                $shortLink->update([
                    'slug' => $user->username,
                    'destination_url' => $profileUrl,
                ]);
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Soft delete the associated short link
        if (! $user->isForceDeleting()) {
            ShortLink::where('user_id', $user->id)
                ->where('slug', $user->username)
                ->delete();
        }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        // Restore the associated short link
        ShortLink::withTrashed()
            ->where('user_id', $user->id)
            ->where('slug', $user->username)
            ->restore();
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        // Force delete the associated short link
        ShortLink::withTrashed()
            ->where('user_id', $user->id)
            ->where('slug', $user->username)
            ->forceDelete();
    }
}
