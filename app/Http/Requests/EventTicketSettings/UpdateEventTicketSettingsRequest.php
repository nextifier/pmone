<?php

namespace App\Http\Requests\EventTicketSettings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventTicketSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('events.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tickets_enabled' => ['sometimes', 'boolean'],
            'business_matching_enabled' => ['sometimes', 'boolean'],
            'allow_cross_day' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'string', 'timezone'],

            // Per-event ticket defaults persisted into events.settings['tickets'].
            'default_min_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'default_max_quantity' => ['sometimes', 'nullable', 'integer', 'min:1', 'gte:default_min_quantity'],
            'default_stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'default_print_on_redeem' => ['sometimes', 'boolean'],
            'login_button_enabled' => ['sometimes', 'boolean'],

            // Staff-managed purchase terms (HTML) per locale, shown at checkout.
            'terms' => ['sometimes', 'nullable', 'array'],
            'terms.en' => ['nullable', 'string'],
            'terms.id' => ['nullable', 'string'],
            'terms.ja' => ['nullable', 'string'],
            'terms.ko' => ['nullable', 'string'],
            'terms.zh' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_max_quantity.gte' => 'Default max quantity must be greater than or equal to default min quantity.',
            'timezone.timezone' => 'Please choose a valid timezone.',
        ];
    }
}
