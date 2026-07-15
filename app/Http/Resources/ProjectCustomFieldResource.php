<?php

namespace App\Http\Resources;

use App\Models\CustomField;
use App\Support\FormFieldTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Brand-field shape for the admin frontend. Project brand fields now live on
 * the centralized CustomField model, but the consumers (CustomFieldsManager,
 * FormBrandProfile) still expect the legacy ProjectCustomField keys: a plain
 * localized `label` string, `options` as a flat string list, `is_required`
 * derived from validation.required, and the `year_select` alias for select
 * fields backed by the `years` options preset. New centralized keys (ulid,
 * label_translations, validation, settings, is_active) are exposed additively.
 *
 * @mixin CustomField
 */
class ProjectCustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isYearSelect = ($this->settings['options_preset'] ?? null) === 'years';

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'label' => $this->label,
            'label_translations' => $this->getTranslations('label'),
            'key' => $this->key,
            'type' => $isYearSelect ? 'year_select' : $this->type,
            'options' => $isYearSelect ? null : $this->legacyOptions(),
            'is_required' => (bool) ($this->validation['required'] ?? false),
            // Absent settings.public defaults to visible; only an explicit false
            // hides the field from the public brand page + live preview.
            'is_public' => ($this->settings['public'] ?? true) !== false,
            'validation' => $this->validation,
            'settings' => $this->settings,
            'is_active' => $this->is_active,
            'order_column' => $this->order_column,
        ];
    }

    /**
     * Flatten canonical `[{value, label}]` options back to the plain string
     * list the legacy frontend renders and stores values against.
     *
     * @return array<int, string>|null
     */
    protected function legacyOptions(): ?array
    {
        if ($this->options === null) {
            return null;
        }

        return array_column(FormFieldTypes::normalizeOptions($this->options), 'value');
    }
}
