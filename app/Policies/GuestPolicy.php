<?php

namespace App\Policies;

use App\Models\Guest;
use App\Models\User;

class GuestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('guests.read');
    }

    public function view(User $user, Guest $guest): bool
    {
        return $user->can('guests.read');
    }

    public function create(User $user): bool
    {
        return $user->can('guests.create');
    }

    public function update(User $user, Guest $guest): bool
    {
        return $user->can('guests.update');
    }

    public function delete(User $user, Guest $guest): bool
    {
        return $user->can('guests.delete');
    }

    public function restore(User $user, Guest $guest): bool
    {
        return $user->can('guests.restore');
    }

    public function forceDelete(User $user, Guest $guest): bool
    {
        return $user->can('guests.delete');
    }

    public function reorder(User $user): bool
    {
        return $user->can('guests.update');
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->can('guests.update');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('guests.delete');
    }
}
