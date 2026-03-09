<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactFormSubmissionResource extends JsonResource
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
            'form_data' => $this->form_data,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Project relationship
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'ulid' => $this->project->ulid,
                'name' => $this->project->name,
                'username' => $this->project->username,
                'email' => $this->project->email,
            ]),

        ];
    }
}
