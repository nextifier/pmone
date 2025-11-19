<?php

namespace App\Policies;

use App\Models\ShortLink;
use App\Models\User;

class ShortLinkPolicy
{
    /**
     * Determine if the user can view any short links.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['master', 'admin', 'staff']);
    }

    /**
     * Determine if the user can view the short link.
     */
    public function view(User $user, ShortLink $shortLink): bool
    {
        // Owner can view their own short links
        if ($user->id === $shortLink->user_id) {
            return true;
        }

        // Master and admin can view all short links
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine if the user can create short links.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create short links
        return true;
    }

    /**
     * Determine if the user can update the short link.
     */
    public function update(User $user, ShortLink $shortLink): bool
    {
        // Owner can update their own short links
        if ($user->id === $shortLink->user_id) {
            return true;
        }

        // Master and admin can update all short links
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine if the user can delete the short link.
     */
    public function delete(User $user, ShortLink $shortLink): bool
    {
        // Owner can delete their own short links
        if ($user->id === $shortLink->user_id) {
            return true;
        }

        // Master and admin can delete all short links
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can restore the short link.
     */
    public function restore(User $user, ShortLink $shortLink): bool
    {
        // Owner can restore their own short links
        if ($user->id === $shortLink->user_id) {
            return true;
        }

        // Master and admin can restore all short links
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can restore any short links (for bulk operations).
     */
    public function restoreAny(User $user): bool
    {
        // All authenticated users can restore short links they own
        // Individual authorization will be checked per short link in the controller
        return true;
    }

    /**
     * Determine whether the user can permanently delete the short link.
     */
    public function forceDelete(User $user, ShortLink $shortLink): bool
    {
        // Owner can force delete their own short links
        if ($user->id === $shortLink->user_id) {
            return true;
        }

        // Master and admin can force delete all short links
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can force delete any short links (for bulk operations).
     */
    public function forceDeleteAny(User $user): bool
    {
        // All authenticated users can force delete short links they own
        // Individual authorization will be checked per short link in the controller
        return true;
    }
}
