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
            'projects_count' => $this->whenCounted('projects'),
            'source' => $this->source,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
