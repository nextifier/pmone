<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available' => (int) $this['available'],
            'requested_qty' => (int) $this['qty'],
            'is_available' => (int) $this['available'] >= (int) $this['qty'],
            'rate_per_night' => (float) ($this['rate_per_night'] ?? 0),
            'all_in_per_night' => (float) ($this['all_in_per_night'] ?? 0),
            'subtotal' => (float) ($this['subtotal'] ?? 0),
            'estimated_total' => (float) ($this['estimated_total'] ?? 0),
            'pricing_type' => $this['pricing_type'] ?? 'flat',
            'daily_breakdown' => $this['daily_breakdown'] ?? [],
        ];
    }
}
