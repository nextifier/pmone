<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'job_title' => $this->job_title,
            'emails' => $this->emails ?? [],
            'phones' => $this->phones ?? [],
            'company_name' => $this->company_name,
            'website' => $this->website,
            'address' => $this->address,
            'notes' => $this->notes,
            'source' => $this->source,
            'more_details' => $this->more_details,
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
                'username' => $project->username,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
