<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a form-context CustomField. `label`/`placeholder`/`help_text`
 * are localized strings (public consumers keep their old shape); the
 * `*_translations` maps are additive for the admin builder's locale tabs.
 */
class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
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
            'order_column' => $this->order_column,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
