<?php

namespace App\Http\Resources;

use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing shape for centralized CustomField definitions. `label`,
 * `placeholder`, and `help_text` resolve to the current locale while their
 * `*_translations` counterparts expose the full translation maps for editing.
 *
 * @mixin CustomField
 */
class CustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'context' => $this->context,
            'type' => $this->type,
            'label' => $this->label,
            'label_translations' => $this->getTranslations('label'),
            'placeholder' => $this->placeholder,
            'placeholder_translations' => $this->getTranslations('placeholder'),
            'help_text' => $this->help_text,
            'help_text_translations' => $this->getTranslations('help_text'),
            'options' => $this->options,
            'validation' => $this->validation,
            'settings' => $this->settings,
            'key' => $this->key,
            'system_key' => $this->system_key,
            'is_active' => $this->is_active,
            'order_column' => $this->order_column,
        ];
    }
}
