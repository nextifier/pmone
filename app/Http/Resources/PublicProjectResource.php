<?php

namespace App\Http\Resources;

use App\Support\HomeSectionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-facing project payload for unauthenticated/API-key routes
 * (`PublicProjectController`). Mirrors the authenticated `ProjectResource`
 * detail branch EXCEPT it never returns the raw `settings` blob, which holds
 * internal/PII data (contact-form email routing, hotel notification emails,
 * email-subject templates). Event websites already source display settings
 * from the curated `website-settings` endpoint and do not read anything off
 * the raw `settings` here, so it is omitted entirely rather than partially
 * allowlisted.
 *
 * The member roster is also dropped: the event websites do not render it, so
 * there is no reason to expose the project's staff roster on a public route.
 *
 * Every other field mirrors `ProjectResource`'s detail branch unchanged.
 */
class PublicProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,
            'email' => $this->email,
            'phone' => $this->phone,
            'user_id' => $this->created_by,
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
