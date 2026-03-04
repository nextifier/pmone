<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

class FormPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Form $form): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        if ($form->user_id === $user->id || $form->created_by === $user->id) {
            return true;
        }

        if ($form->project_id) {
            return $form->project->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('forms.create');
    }

    public function update(User $user, Form $form): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $form->user_id === $user->id || $form->created_by === $user->id;
    }

    public function delete(User $user, Form $form): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $form->user_id === $user->id || $form->created_by === $user->id;
    }

    public function restore(User $user, Form $form): bool
    {
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        return $form->user_id === $user->id || $form->created_by === $user->id;
    }

    public function forceDelete(User $user, Form $form): bool
    {
        return $user->hasRole(['master', 'admin']);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole(['master', 'admin']);
    }
}
