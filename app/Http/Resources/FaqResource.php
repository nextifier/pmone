<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin resource — exposes ALL locale translations (with raw {{tokens}}) so the
 * form can edit per-locale. Tokens are resolved only by the public resource.
 */
class FaqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,

            'question' => $this->getTranslations('question'),
            'answer' => $this->getTranslations('answer'),

            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
            'settings' => $this->settings ?? [],

            'can_edit' => $user ? $user->can('faqs.update') : false,
            'can_delete' => $user ? $user->can('faqs.delete') : false,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'deleted_by_user' => $this->whenLoaded('deleter', fn () => $this->deleter ? [
                'id' => $this->deleter->id,
                'name' => $this->deleter->name,
            ] : null),
        ];
    }
}
