<?php

namespace App\Http\Resources\Email;

use App\Models\EmailEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailEvent */
class EmailEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'recipient' => $this->recipient === '' ? null : $this->recipient,
            'subtype' => $this->subtype,
            'diagnostic' => $this->diagnostic,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
