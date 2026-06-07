<?php

namespace App\Policies;

use App\Models\MediaCoverage;
use App\Models\User;

class MediaCoveragePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media_coverages.read');
    }

    public function view(User $user, MediaCoverage $mediaCoverage): bool
    {
        return $user->can('media_coverages.read');
    }

    public function create(User $user): bool
    {
        return $user->can('media_coverages.create');
    }

    public function update(User $user, MediaCoverage $mediaCoverage): bool
    {
        return $user->can('media_coverages.update');
    }

    public function delete(User $user, MediaCoverage $mediaCoverage): bool
    {
        return $user->can('media_coverages.delete');
    }

    public function restore(User $user, MediaCoverage $mediaCoverage): bool
    {
        return $user->can('media_coverages.restore');
    }

    public function forceDelete(User $user, MediaCoverage $mediaCoverage): bool
    {
        return $user->can('media_coverages.delete');
    }

    public function reorder(User $user): bool
    {
        return $user->can('media_coverages.update');
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->can('media_coverages.update');
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('media_coverages.delete');
    }
}
