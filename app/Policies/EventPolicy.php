<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Project;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view any events for a project.
     */
    public function viewAny(User $user, Project $project): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can view the event.
     */
    public function view(User $user, Event $event): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $event->project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(User $user, Project $project): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $event->project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        if ($event->created_by === $user->id) {
            return true;
        }

        return $event->project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can restore the event.
     */
    public function restore(User $user, Event $event): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $event->project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can permanently delete the event.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->hasRole(['master', 'admin']);
    }

    /**
     * Determine whether the user can update event ordering.
     */
    public function updateOrder(User $user, Project $project): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $project->members()->where('user_id', $user->id)->exists();
    }
}
