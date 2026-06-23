<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'username' => $this->author->username,
                'profile_image' => $this->author->hasMedia('profile_image')
                    ? $this->author->getMediaUrls('profile_image')
                    : null,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'time_ago' => $this->created_at?->diffForHumans(),
        ];
    }
}
