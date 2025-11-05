<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\ShortLink;

class ProjectObserver
{
    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        // Create a short link for the project profile
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $profileUrl = rtrim($frontendUrl, '/').'/projects/'.$project->username;

        ShortLink::create([
            'user_id' => $project->created_by ?? auth()->id(),
            'slug' => $project->username,
            'destination_url' => $profileUrl,
            'is_active' => true,
        ]);
    }

    /**
     * Handle the Project "updated" event.
     */
    public function updated(Project $project): void
    {
        // If username changed, update the associated short link
        if ($project->isDirty('username')) {
            $oldUsername = $project->getOriginal('username');

            // Find the short link with the old username
            $shortLink = ShortLink::where('slug', $oldUsername)
                ->first();

            if ($shortLink) {
                $frontendUrl = config('app.frontend_url', config('app.url'));
                $profileUrl = rtrim($frontendUrl, '/').'/projects/'.$project->username;

                $shortLink->update([
                    'slug' => $project->username,
                    'destination_url' => $profileUrl,
                ]);
            }
        }
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        // Soft delete the associated short link
        if (! $project->isForceDeleting()) {
            ShortLink::where('slug', $project->username)
                ->delete();
        }
    }

    /**
     * Handle the Project "restored" event.
     */
    public function restored(Project $project): void
    {
        // Restore the associated short link
        ShortLink::withTrashed()
            ->where('slug', $project->username)
            ->restore();
    }

    /**
     * Handle the Project "force deleted" event.
     */
    public function forceDeleted(Project $project): void
    {
        // Force delete the associated short link
        ShortLink::withTrashed()
            ->where('slug', $project->username)
            ->forceDelete();
    }
}
