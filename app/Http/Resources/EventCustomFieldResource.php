<?php

namespace App\Http\Resources;

use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an event-scoped CustomField (business_matching and
 * ticket_registration contexts). The output keys predate the unification and
 * are consumed by the 11 live event websites, so they stay byte-compatible:
 * `event_id` reads the fieldable morph, `required` derives from
 * validation.required. `ulid`, `validation`, and friends are additive.
 *
 * @mixin CustomField
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
            'ulid' => $this->ulid,
            'event_id' => $this->event_id,
            'context' => $this->context,
            // Localized label for the public/visitor form (falls back to English);
            // label_translations carries every locale for the admin builder.
            'label' => $this->getTranslation('label', app()->getLocale(), false)
                ?: $this->getTranslation('label', 'en', false),
            'label_translations' => $this->getTranslations('label'),
            'placeholder' => $this->placeholder,
            'help_text' => $this->help_text,
            'type' => $this->type,
            'options' => $this->options ?? [],
            'required' => (bool) ($this->validation['required'] ?? false),
            'validation' => $this->validation,
            'settings' => $this->settings,
            'system_key' => $this->system_key,
            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
        ];
    }
}
