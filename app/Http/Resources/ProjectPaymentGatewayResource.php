<?php

namespace App\Http\Resources;

use App\Models\ProjectPaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Always masks credential fields. Never expose plaintext secrets via API.
 *
 * @mixin ProjectPaymentGateway
 */
class ProjectPaymentGatewayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'project_id' => $this->project_id,
            'provider' => $this->provider,
            'label' => $this->label,
            'mode' => $this->mode,
            'is_active' => $this->is_active,
            'secret_key_masked' => ProjectPaymentGateway::mask($this->secret_key),
            'webhook_token_masked' => ProjectPaymentGateway::mask($this->webhook_token),
            'public_key' => $this->public_key,
            'has_secret_key' => filled($this->secret_key),
            'has_webhook_token' => filled($this->webhook_token),
            'config' => $this->config ?? [],
            'webhook_url' => $this->resolveWebhookUrl(),
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveWebhookUrl(): ?string
    {
        if (! $this->relationLoaded('project') && ! $this->project_id) {
            return null;
        }

        $username = $this->project?->username;

        if (! $username) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/api/webhooks/'.$this->provider.'/'.$username;
    }
}
