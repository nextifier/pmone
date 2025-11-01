<?php

namespace App\Listeners;

use App\Models\User;
use Spatie\Permission\Events\RoleAttached;

class AutoVerifyPrivilegedUsers
{
    /**
     * Handle the event when a role is attached to a user
     */
    public function handle(RoleAttached $event): void
    {
        // Get the user (model) and roles that were attached
        $user = $event->model;
        $rolesOrIds = $event->rolesOrIds;

        // Only process if it's a User model
        if (! $user instanceof User) {
            return;
        }

        // Skip if user is already verified
        if ($user->hasVerifiedEmail()) {
            return;
        }

        // Get role names from the attached roles
        $roleNames = [];
        if (is_array($rolesOrIds)) {
            foreach ($rolesOrIds as $roleOrId) {
                if (is_object($roleOrId) && method_exists($roleOrId, 'name')) {
                    $roleNames[] = $roleOrId->name;
                } elseif (is_string($roleOrId) || is_numeric($roleOrId)) {
                    // Get role by ID or name
                    $role = \Spatie\Permission\Models\Role::where('id', $roleOrId)
                        ->orWhere('name', $roleOrId)
                        ->first();
                    if ($role) {
                        $roleNames[] = $role->name;
                    }
                }
            }
        } elseif (is_object($rolesOrIds) && method_exists($rolesOrIds, 'name')) {
            $roleNames[] = $rolesOrIds->name;
        } elseif (is_string($rolesOrIds) || is_numeric($rolesOrIds)) {
            $role = \Spatie\Permission\Models\Role::where('id', $rolesOrIds)
                ->orWhere('name', $rolesOrIds)
                ->first();
            if ($role) {
                $roleNames[] = $role->name;
            }
        }

        // Auto-verify if user gets master or admin role
        $privilegedRoles = array_intersect($roleNames, ['master', 'admin']);
        if (! empty($privilegedRoles)) {
            $user->markEmailAsVerified();

            logger()->info('User auto-verified due to privileged role assignment', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $privilegedRoles,
            ]);
        }
    }
}
