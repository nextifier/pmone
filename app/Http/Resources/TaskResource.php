<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For list view (index, trash) - minimal data
        if ($request->routeIs('tasks.index') || $request->routeIs('tasks.trash')) {
            return [
                'id' => $this->id,
                'ulid' => $this->ulid,
                'title' => $this->title,
                'status' => $this->status,
                'priority' => $this->priority,
                'complexity' => $this->complexity,
                'visibility' => $this->visibility,
                'estimated_start_at' => $this->estimated_start_at,
                'estimated_completion_at' => $this->estimated_completion_at,
                'completed_at' => $this->completed_at,
                'is_overdue' => $this->isOverdue(),
                'is_completed' => $this->isCompleted(),
                'order_column' => $this->order_column,
                'assignee' => $this->whenLoaded('assignee', fn () => new UserMinimalResource($this->assignee)),
                'project' => $this->whenLoaded('project', fn () => [
                    'id' => $this->project->id,
                    'ulid' => $this->project->ulid,
                    'name' => $this->project->name,
                    'username' => $this->project->username,
                    'profile_image' => $this->project->hasMedia('profile_image')
                        ? $this->project->getMediaUrls('profile_image')
                        : null,
                    'more_details' => $this->project->more_details,
                ]),
                'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'deleted_at' => $this->deleted_at,
            ];
        }

        // For detail view (show, edit) - complete data
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'complexity' => $this->complexity,
            'visibility' => $this->visibility,
            'estimated_start_at' => $this->estimated_start_at,
            'estimated_completion_at' => $this->estimated_completion_at,
            'completed_at' => $this->completed_at,
            'is_overdue' => $this->isOverdue(),
            'is_completed' => $this->isCompleted(),
            'order_column' => $this->order_column,

            // Relationships
            'assignee' => $this->whenLoaded('assignee', fn () => new UserResource($this->assignee)),
            'project' => $this->whenLoaded('project', fn () => new ProjectResource($this->project)),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'shared_users' => $this->whenLoaded('sharedUsers', fn () =>
                $this->sharedUsers->map(fn ($user) => [
                    'id' => $user->id,
                    'ulid' => $user->ulid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                ])
            ),

            // Media (description images)
            'description_images' => $this->when(
                $this->hasMedia('description_images'),
                $this->getMediaUrls('description_images')
            ),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
