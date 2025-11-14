<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Anyone can view list of posts
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Post $post): bool
    {
        // Published public posts can be viewed by anyone
        if ($post->status === 'published' && $post->visibility === 'public') {
            return true;
        }

        // Guest users cannot view private/members_only or draft posts
        if (! $user) {
            return false;
        }

        // Master and admin can view all posts
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Authors can view their own posts regardless of status
        if ($post->authors()->where('users.id', $user->id)->exists()) {
            return true;
        }

        // Primary author (creator) can view
        if ($post->created_by === $user->id) {
            return true;
        }

        // Members-only posts can be viewed by authenticated users
        if ($post->status === 'published' && $post->visibility === 'members_only') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create posts
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool
    {
        // Master and admin can update all posts
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Authors can update their posts
        if ($post->authors()->where('users.id', $user->id)->exists()) {
            return true;
        }

        // Primary author (creator) can update
        if ($post->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        // Master and admin can delete all posts
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Only primary author (creator) can delete posts, not co-authors
        return $post->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Post $post): bool
    {
        // Master and admin can restore all posts
        if ($user->hasRole(['master', 'admin'])) {
            return true;
        }

        // Primary author can restore their posts
        return $post->created_by === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Post $post): bool
    {
        // Only master and admin can force delete posts
        return $user->hasRole(['master', 'admin']);
    }
}
