<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('programs.read');
    }

    public function view(User $user, Program $program): bool
    {
        return $user->can('programs.read');
    }

    public function create(User $user): bool
    {
        return $user->can('programs.create');
    }

    public function update(User $user, Program $program): bool
    {
        return $user->can('programs.update');
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->can('programs.delete');
    }

    public function restore(User $user, Program $program): bool
    {
        return $user->can('programs.restore');
    }

    public function forceDelete(User $user, Program $program): bool
    {
        return $user->can('programs.delete');
    }

    public function reorder(User $user): bool
    {
        return $user->can('programs.update');
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->can('programs.update');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('programs.delete');
    }
}
