<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostAutosaveResource extends JsonResource
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
            'post_id' => $this->post_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'content_format' => $this->content_format,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'published_at' => $this->published_at,
            'featured' => $this->featured,
            'reading_time' => $this->reading_time,
            'settings' => $this->settings,
            'tmp_media' => $this->tmp_media,
            'tags' => $this->tags,
            'authors' => $this->authors,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_for_new_post' => $this->isForNewPost(),
            'is_for_existing_post' => $this->isForExistingPost(),
        ];
    }
}
