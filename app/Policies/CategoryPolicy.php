<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Anyone can view list of categories
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Category $category): bool
    {
        // Public categories can be viewed by anyone
        if ($category->visibility === 'public') {
            return true;
        }

        // Guest users cannot view private categories
        if (! $user) {
            return false;
        }

        // Master and admin can view all categories
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Staff can view all categories
        if ($user->hasRole(['staff'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only master, admin, and staff can create categories
        return $user->hasRole(['master', 'admin', 'staff']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        // Only master, admin, and staff can update categories
        return $user->hasRole(['master', 'admin', 'staff']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        // Only master and admin can delete categories
        return $user->hasRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Category $category): bool
    {
        // Only master and admin can restore categories
        return $user->hasRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        // Only master and admin can force delete categories
        return $user->hasRole(['master', 'admin']);
    }
}
