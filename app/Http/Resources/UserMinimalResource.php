<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserMinimalResource extends JsonResource
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
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'title' => $this->title,
            'profile_image' => $this->when(
                $this->hasMedia('profile_image'),
                $this->getMediaUrls('profile_image')
            ),
            'pivot' => $this->when(
                isset($this->pivot),
                [
                    'role' => $this->pivot->role ?? null,
                    'order' => $this->pivot->order ?? 0,
                ]
            ),
        ];
    }
}
