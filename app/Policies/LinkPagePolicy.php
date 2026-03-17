<?php

namespace App\Policies;

use App\Models\LinkPage;
use App\Models\User;

class LinkPagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LinkPage $linkPage): bool
    {
        if ($user->id === $linkPage->user_id) {
            return true;
        }

        return $user->hasAnyRole(['master', 'admin', 'staff']);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LinkPage $linkPage): bool
    {
        if ($user->id === $linkPage->user_id) {
            return true;
        }

        return $user->hasAnyRole(['master', 'admin', 'staff']);
    }

    public function delete(User $user, LinkPage $linkPage): bool
    {
        if ($user->id === $linkPage->user_id) {
            return true;
        }

        return $user->hasAnyRole(['master', 'admin', 'staff']);
    }

    public function restore(User $user, LinkPage $linkPage): bool
    {
        if ($user->id === $linkPage->user_id) {
            return true;
        }

        return $user->hasAnyRole(['master', 'admin', 'staff']);
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user, LinkPage $linkPage): bool
    {
        if ($user->id === $linkPage->user_id) {
            return true;
        }

        return $user->hasAnyRole(['master', 'admin', 'staff']);
    }

    public function forceDeleteAny(User $user): bool
    {
        return true;
    }
}
