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
            'field_values' => $this->field_values,
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
            // The current version drives the primary file display; superseded
            // versions are exposed separately via `file_history`.
            'submission_file' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('submission_file'),
                fn () => $this->currentSubmissionFileUrls()
            ),
            'files' => $this->when(
                $this->relationLoaded('media'),
                fn () => $this->currentSubmissionFiles()->map(fn ($media) => [
                    'id' => $media->id,
                    'field_ulid' => $media->getCustomProperty('field_ulid'),
                    'name' => $media->file_name,
                    'url' => $media->getUrl(),
                    'size' => $media->size,
                ])->values()
            ),
            // Full per-field version history (current + superseded), so the
            // exhibitor sees the same audit trail as staff.
            'file_history' => $this->when(
                $this->relationLoaded('media'),
                fn () => $this->fileHistory()
            ),
            'event_document' => new EventDocumentResource($this->whenLoaded('eventDocument')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
