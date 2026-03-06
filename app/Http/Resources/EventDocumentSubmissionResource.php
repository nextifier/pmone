<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDocumentSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'event_document_id' => $this->event_document_id,
            'booth_identifier' => $this->booth_identifier,
            'event_id' => $this->event_id,
            'agreed_at' => $this->agreed_at,
            'text_value' => $this->text_value,
            'document_version' => $this->document_version,
            'needs_reagreement' => $this->when(
                $this->relationLoaded('eventDocument'),
                fn () => $this->needsReagreement()
            ),
            'submitted_by' => $this->submitted_by,
            'submitter' => $this->when(
                $this->relationLoaded('submitter'),
                fn () => [
                    'id' => $this->submitter->id,
                    'name' => $this->submitter->name,
                    'email' => $this->submitter->email,
                ]
            ),
            'submitted_at' => $this->submitted_at,
            'submission_file' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('submission_file'),
                fn () => $this->getMediaUrls('submission_file')
            ),
            'event_document' => new EventDocumentResource($this->whenLoaded('eventDocument')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
