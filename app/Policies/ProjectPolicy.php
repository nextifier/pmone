<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Anyone can view list of public projects
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Project $project): bool
    {
        // Public projects can be viewed by anyone
        if ($project->visibility === 'public') {
            return true;
        }

        // Guest users cannot view private/members_only projects
        if (! $user) {
            return false;
        }

        // Master and admin can view all projects
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Members can view members_only and private projects
        if ($project->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only master, admin, and staff can create projects
        return $user->hasRole(['master', 'admin', 'staff']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        // Master and admin can update all projects
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Project members can update the project
        return $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        // Master and admin can delete all projects
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Creator can delete their own project
        if ($project->created_by === $user->id) {
            return true;
        }

        // Members can delete projects they belong to
        return $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        // Master and admin can restore all projects
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Creator can restore their own project
        if ($project->created_by === $user->id) {
            return true;
        }

        // Members can restore projects they belong to
        return $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can restore any projects (for bulk operations).
     */
    public function restoreAny(User $user): bool
    {
        // All authenticated users can attempt to restore projects
        // Individual authorization will be checked per project in the controller
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        // Only master and admin can force delete projects
        return $user->hasRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can force delete any projects (for bulk operations).
     */
    public function forceDeleteAny(User $user): bool
    {
        // Only master and admin can force delete projects
        return $user->hasRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can update project ordering.
     */
    public function updateOrder(User $user): bool
    {
        // Only master, admin, and staff can reorder projects
        return $user->hasRole(['master', 'admin', 'staff']);
    }
}
