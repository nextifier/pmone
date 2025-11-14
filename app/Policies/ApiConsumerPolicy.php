<?php

namespace App\Policies;

use App\Models\ApiConsumer;
use App\Models\User;

class ApiConsumerPolicy
{
    /**
     * Determine if the user can view any API consumers
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine if the user can view the API consumer
     */
    public function view(User $user, ApiConsumer $apiConsumer): bool
    {
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine if the user can create API consumers
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine if the user can update the API consumer
     */
    public function update(User $user, ApiConsumer $apiConsumer): bool
    {
        return $user->hasAnyRole(['master', 'admin']);
    }

    /**
     * Determine if the user can delete the API consumer
     */
    public function delete(User $user, ApiConsumer $apiConsumer): bool
    {
        // Only master can delete API consumers
        return $user->hasRole('master');
    }

    /**
     * Determine if the user can restore the API consumer
     */
    public function restore(User $user, ApiConsumer $apiConsumer): bool
    {
        return $user->hasRole('master');
    }

    /**
     * Determine if the user can permanently delete the API consumer
     */
    public function forceDelete(User $user, ApiConsumer $apiConsumer): bool
    {
        return $user->hasRole('master');
    }
}
