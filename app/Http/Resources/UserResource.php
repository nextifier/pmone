<?php

namespace App\Http\Resources;

use App\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    protected bool $includePresence = true;

    /**
     * Strip is_online / last_seen from the payload. Used by the cached public
     * /resolve branch: presence has a 5-minute semantic window, so serving it
     * from a response cached for an hour is wrong in both directions, and
     * last_seen is deliberately excluded from the cache-busting field list.
     */
    public function withoutPresence(): static
    {
        $this->includePresence = false;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $canViewSecurity = (bool) auth()->user()?->can('users.view_security');
        $isMaster = (bool) auth()->user()?->hasRole('master');

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
            'is_online' => $this->when($this->includePresence, fn () => $this->isOnline()),
            // email_verified_at and the two_factor_* flags are gated to
            // authenticated callers: the anonymous /resolve payload is cached
            // for an hour and the self-serve verify/2FA writes never bust it
            // (they sit outside User::PUBLIC_PROFILE_FIELDS) - and a stranger's
            // 2FA status is not public information in the first place.
            'email_verified_at' => $this->when(auth()->check(), fn () => $this->email_verified_at?->toISOString()),
            'last_seen' => $this->when($this->includePresence, fn () => $this->last_seen?->toISOString()),
            'last_page' => $this->when($isMaster && $this->last_page, fn () => [
                'path' => $this->last_page,
                'title' => $this->last_page_title,
            ]),
            'last_login_at' => $this->when($canViewSecurity, fn () => $this->last_login_at?->toISOString()),
            'last_login_ip' => $this->when($canViewSecurity, fn () => $this->last_login_ip),
            'last_login_device' => $this->when($canViewSecurity, fn () => UserAgentParser::parse($this->last_login_user_agent)),
            'suspended_at' => $this->when($canViewSecurity, fn () => $this->suspended_at?->toISOString()),
            'suspension_reason' => $this->when($canViewSecurity, fn () => $this->suspension_reason),
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
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'username' => $p->username,
                'profile_image' => $p->hasMedia('profile_image') ? $p->getMediaUrls('profile_image') : null,
            ])),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $this->relationLoaded('permissions') || $this->relationLoaded('roles'),
                fn () => $this->getAllPermissions()->pluck('name')
            ),
            'two_factor_enabled' => $this->when(auth()->check(), fn () => ! is_null($this->two_factor_secret)),
            'two_factor_confirmed' => $this->when(auth()->check(), fn () => ! is_null($this->two_factor_confirmed_at)),
            'oauth_providers' => $this->whenLoaded(
                'oauthProviders',
                fn () => $this->oauthProviders->pluck('provider')
            ),
        ];
    }
}
