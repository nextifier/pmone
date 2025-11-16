<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactFormSubmissionIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'form_data_preview' => [
                'name' => data_get($this->form_data, 'name'),
                'email' => data_get($this->form_data, 'email'),
                'phone' => data_get($this->form_data, 'phone'),
            ],
            'followed_up_at' => $this->followed_up_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Project relationship
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'ulid' => $this->project->ulid,
                'name' => $this->project->name,
                'username' => $this->project->username,
            ]),

            // Followed up by user
            'followed_up_by_user' => $this->whenLoaded('followedUpByUser', fn () => $this->followedUpByUser ? [
                'id' => $this->followedUpByUser->id,
                'name' => $this->followedUpByUser->name,
                'username' => $this->followedUpByUser->username,
            ] : null),
        ];
    }
}
