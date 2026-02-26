<?php

namespace App\Policies;

use App\Models\BrandEvent;
use App\Models\User;

class BrandEventPolicy
{
    /**
     * Staff+ or brand member can view.
     */
    public function view(User $user, BrandEvent $brandEvent): bool
    {
        if ($user->hasRole(['master', 'admin', 'staff'])) {
            return true;
        }

        return $brandEvent->brand->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Staff+ or brand member can update (profile, promotion posts).
     */
    public function update(User $user, BrandEvent $brandEvent): bool
    {
        if ($user->hasRole(['master', 'admin', 'staff'])) {
            return true;
        }

        return $brandEvent->brand->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Staff+ only can update booth info.
     */
    public function updateBooth(User $user, BrandEvent $brandEvent): bool
    {
        return $user->hasRole(['master', 'admin', 'staff']);
    }

    /**
     * Staff+ only can manage members.
     */
    public function manageMember(User $user, BrandEvent $brandEvent): bool
    {
        return $user->hasRole(['master', 'admin', 'staff']);
    }
}
