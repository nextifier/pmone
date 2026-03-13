<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $emails = $this->emails ?? [];
        $phones = $this->phones ?? [];

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'job_title' => $this->job_title,
            'emails' => $emails,
            'phones' => $phones,
            'primary_email' => $emails[0] ?? null,
            'primary_phone' => $phones[0] ?? null,
            'company_name' => $this->company_name,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'contact_types' => $this->contact_types_list,
            'business_categories' => $this->business_categories_list,
            'tags' => $this->tags_list,
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'profile_image' => $project->hasMedia('profile_image')
                    ? $project->getMediaUrls('profile_image')
                    : null,
            ])),
            'projects_count' => $this->whenCounted('projects'),
            'source' => $this->source,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
