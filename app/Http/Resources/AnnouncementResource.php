<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isListView = $request->routeIs('announcements.index') || $request->routeIs('announcements.trash');
        $isPublicView = $request->routeIs('dashboard.announcements');

        if ($isPublicView) {
            return $this->publicShape();
        }

        if ($isListView) {
            return $this->listShape();
        }

        return $this->detailShape();
    }

    private function publicShape(): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'type' => $this->type,
            'cta_actions' => $this->cta_actions ?? [],
            'is_dismissible' => (bool) $this->is_dismissible,
            'image' => $this->getImageUrls(),
            'more_details' => $this->more_details,
            'order_column' => $this->order_column,
        ];
    }

    private function listShape(): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->title,
            'icon' => $this->icon,
            'type' => $this->type,
            'status' => $this->status,
            'is_global' => (bool) $this->is_global,
            'is_dismissible' => (bool) $this->is_dismissible,
            'target_roles' => $this->target_roles ?? [],
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'order_column' => $this->order_column,
            'image' => $this->getImageUrls(),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'dismissals_count' => $this->dismissals_count ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function detailShape(): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'type' => $this->type,
            'status' => $this->status,
            'is_global' => (bool) $this->is_global,
            'is_dismissible' => (bool) $this->is_dismissible,
            'target_roles' => $this->target_roles ?? [],
            'cta_actions' => $this->cta_actions ?? [],
            'more_details' => $this->more_details,
            'settings' => $this->settings,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'order_column' => $this->order_column,
            'image' => $this->getImageUrls(),
            'target_users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])),
            'target_events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
            ])),
            'target_projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function getImageUrls(): mixed
    {
        if ($this->relationLoaded('media') && $this->hasMedia('image')) {
            return $this->getMediaUrlsDetailed('image');
        }

        return null;
    }
}
