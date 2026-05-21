<?php

namespace App\Http\Resources\Payment;

use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentWebhookEvent
 */
class PaymentWebhookEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'provider' => $this->provider,
            'event_type' => $this->event_type,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'http_status' => $this->http_status,
            'message' => $this->message,
            'payload' => $this->payload,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
