<?php

namespace App\Http\Resources\Email;

use App\Models\EmailSuppression;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailSuppression */
class EmailSuppressionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'subtype' => $this->subtype,
            'source' => $this->source,
            'suppressed_at' => $this->suppressed_at?->toIso8601String(),
            // SES nests the reason under bouncedRecipients; Resend puts it on the
            // bounce object. Fall back through both shapes.
            'diagnostic' => $this->payload['bouncedRecipients'][0]['diagnosticCode']
                ?? $this->payload['bounce']['message']
                ?? null,
        ];
    }
}
