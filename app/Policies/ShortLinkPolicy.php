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
}
