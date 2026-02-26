<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    /**
     * Staff+ or exhibitor assigned to any brand can view.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(['master', 'admin', 'staff'])) {
            return true;
        }

        // Exhibitor can view their own brands
        return $user->brands()->exists();
    }

    /**
     * Staff+ or exhibitor assigned to brand can view.
     */
    public function view(User $user, Brand $brand): bool
    {
        if ($user->hasRole(['master', 'admin', 'staff'])) {
            return true;
        }

        return $brand->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Staff+ only can create.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['master', 'admin', 'staff']);
    }

    /**
     * Staff+ or exhibitor assigned to brand can update.
     */
    public function update(User $user, Brand $brand): bool
    {
        if ($user->hasRole(['master', 'admin', 'staff'])) {
            return true;
        }

        return $brand->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Staff+ only can delete.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $user->hasRole(['master', 'admin', 'staff']);
    }
}
