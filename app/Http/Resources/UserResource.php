<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'title' => $this->title,
            'bio' => $this->bio,
            'links' => LinkResource::collection($this->whenLoaded('links')),
            'user_settings' => $this->when(
                $this->id === auth()->id() || auth()->user()?->can('users.view.settings'),
                $this->user_settings
            ),
            'more_details' => $this->when(
                $this->id === auth()->id() || auth()->user()?->can('users.view.details'),
                $this->more_details
            ),
            'status' => $this->status,
            'visibility' => $this->visibility,
            'is_online' => $this->isOnline(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_seen' => $this->last_seen?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'profile_image' => $this->when(
                $this->hasMedia('profile_image'),
                fn () => $this->getMediaUrls('profile_image')
            ),
            'cover_image' => $this->when(
                $this->hasMedia('cover_image'),
                fn () => $this->getMediaUrls('cover_image')
            ),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $this->relationLoaded('permissions') || $this->relationLoaded('roles'),
                fn () => $this->getAllPermissions()->pluck('name')
            ),
            'two_factor_enabled' => ! is_null($this->two_factor_secret),
            'two_factor_confirmed' => ! is_null($this->two_factor_confirmed_at),
            'oauth_providers' => $this->whenLoaded(
                'oauthProviders',
                fn () => $this->oauthProviders->pluck('provider')
            ),
        ];
    }
}
