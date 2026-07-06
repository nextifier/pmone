<?php

namespace App\Http\Resources;

use App\Support\HomeSectionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For list view (index, trash) - minimal data
        if ($request->routeIs('projects.index') || $request->routeIs('projects.trash')) {
            return [
                'id' => $this->id,
                'ulid' => $this->ulid,
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'status' => $this->status,
                'visibility' => $this->visibility,
                'organization' => $this->organization,
                'order_column' => $this->order_column,
                'profile_image' => $this->when(
                    $this->hasMedia('profile_image'),
                    fn () => $this->getMediaUrls('profile_image')
                ),
                'members_count' => $this->whenLoaded('members', fn () => $this->members->count()),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'deleted_at' => $this->deleted_at,
                'members' => $this->whenLoaded('members', fn () => UserMinimalResource::collection($this->members)),
            ];
        }

        // For detail view (show, edit) - complete data
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,
            'email' => $this->email,
            'phone' => $this->phone,
            'user_id' => $this->created_by,
            'settings' => $this->settings,
            // Catalog drives which toggles the Website Settings page renders;
            // home_sections carries the resolved current value per section
            // (stored -> legacy -> default), so the switches reflect real state.
            'home_sections_catalog' => HomeSectionCatalog::forResource($this->username),
            'home_sections' => HomeSectionCatalog::resolveAll(
                data_get($this->settings, 'website_settings', [])
            ),
            'more_details' => $this->more_details,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'organization' => $this->organization,
            'profile_image' => $this->when(
                $this->hasMedia('profile_image'),
                fn () => $this->getMediaUrls('profile_image')
            ),
            'cover_image' => $this->when(
                $this->hasMedia('cover_image'),
                fn () => $this->getMediaUrls('cover_image')
            ),
            'members' => $this->whenLoaded('members', fn () => UserMinimalResource::collection($this->members)),
            'links' => LinkResource::collection($this->whenLoaded('links')),
            'roles' => [],
            'is_member' => auth()->user()
                ? $this->members()->where('user_id', auth()->id())->exists()
                : false,
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'deleter' => $this->whenLoaded('deleter', fn () => new UserMinimalResource($this->deleter)),
            'has_xendit_gateway' => $this->resolvePaymentGateway(
                'xendit',
                app()->environment('production') ? 'live' : 'test'
            ) !== null,
            'has_active_payment_gateway' => $this->hasActivePaymentGateway(),
            'hotel_reservation_enabled' => (bool) $this->hotel_reservation_enabled,
            'xendit_setup_url' => "/projects/{$this->username}/settings/payment-gateways",
            'payment_gateways_url' => "/projects/{$this->username}/settings/payment-gateways",
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
