<?php

namespace App\Http\Resources;

use App\Enums\PaymentCapability;
use App\Models\ProjectPaymentGateway;
use App\Services\Payment\PaymentProviderFactory;
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
            'capabilities' => $this->resolveCapabilities(),
            'webhook_url' => $this->resolveWebhookUrl(),
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Generic webhook URL — single URL for every Xendit account regardless
     * of how many PM One projects share it. The controller resolves the
     * owning project from the payload's `external_id` (reservation_number)
     * and verifies the token against that project's gateway, so per-project
     * URLs are no longer needed. Per-project routes still resolve for any
     * historically configured webhooks.
     */
    private function resolveWebhookUrl(): ?string
    {
        return rtrim(config('app.url'), '/').'/api/webhooks/'.$this->provider;
    }

    /**
     * Capability codes this gateway's provider exposes, so the frontend knows
     * which money-operation panels (balance, transactions, ...) to render.
     * Returns an empty list for an unrecognised provider rather than failing.
     *
     * @return array<int, string>
     */
    private function resolveCapabilities(): array
    {
        try {
            $provider = app(PaymentProviderFactory::class)->make($this->resource);

            return array_map(
                fn (PaymentCapability $capability): string => $capability->value,
                $provider->capabilities(),
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
