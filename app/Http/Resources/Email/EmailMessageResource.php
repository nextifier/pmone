<?php

namespace App\Http\Resources\Email;

use App\Models\EmailMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailMessage */
class EmailMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'mailer' => $this->mailer,
            'from_address' => $this->from_address,
            'subject' => $this->subject,
            'recipients' => $this->recipients,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'last_event_at' => $this->last_event_at?->toIso8601String(),
            'events' => EmailEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
