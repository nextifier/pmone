<?php

namespace App\Http\Resources;

use App\Models\EventCustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventCustomField
 */
class EventCustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            // Localized label for the public/visitor form (falls back to English);
            // label_translations carries every locale for the admin builder.
            'label' => $this->getTranslation('label', app()->getLocale(), false)
                ?: $this->getTranslation('label', 'en', false),
            'label_translations' => $this->getTranslations('label'),
            'type' => $this->type,
            'options' => $this->options ?? [],
            'required' => (bool) $this->required,
            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
        ];
    }
}
