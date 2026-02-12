<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Anyone can view list of public tasks
        // Non-authenticated users will only see public tasks
        // Authenticated users will see their own tasks + public tasks
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Task $task): bool
    {
        // Public tasks can be viewed by anyone
        if ($task->visibility === Task::VISIBILITY_PUBLIC) {
            return true;
        }

        // Guest users cannot view private/shared tasks
        if (! $user) {
            return false;
        }

        // Master and admin can view all tasks
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Creator can view their own tasks (any visibility)
        if ($task->created_by === $user->id) {
            return true;
        }

        // Assignee can view assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        // Shared tasks: check if user is in task_user pivot
        if ($task->visibility === Task::VISIBILITY_SHARED) {
            return $task->sharedUsers()->where('user_id', $user->id)->exists();
        }

        // If task belongs to a project, check project membership
        if ($task->project_id) {
            return $task->project->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tasks.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task): bool
    {
        // Master and admin can update all tasks
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Creator can update their own tasks
        if ($task->created_by === $user->id) {
            return true;
        }

        // Assignee can update their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        // Check if shared user has 'editor' role
        if ($task->visibility === Task::VISIBILITY_SHARED) {
            $sharedUser = $task->sharedUsers()
                ->where('user_id', $user->id)
                ->first();

            if ($sharedUser && $sharedUser->pivot->role === Task::SHARED_ROLE_EDITOR) {
                return true;
            }
        }

        // Project members can update project tasks
        if ($task->project_id) {
            return $task->project->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        // Master and admin can delete all tasks
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Only creator can delete their own tasks
        return $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Task $task): bool
    {
        // Master and admin can restore all tasks
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Only creator can restore their own tasks
        return $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore any tasks (for bulk operations).
     */
    public function restoreAny(User $user): bool
    {
        // All authenticated users can attempt to restore tasks
        // Individual authorization will be checked per task in the controller
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        // Only master and admin can force delete tasks
        return $user->hasRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can force delete any tasks (for bulk operations).
     */
    public function forceDeleteAny(User $user): bool
    {
        // Only master and admin can force delete tasks
        return $user->hasRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can update task ordering.
     */
    public function updateOrder(User $user): bool
    {
        return $user->hasPermissionTo('tasks.update');
    }
}
