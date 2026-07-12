<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * No locale is required: like WebsitePage, a copy override is optional
     * per-locale by design - an admin may clear a locale back to blank to
     * fall back to the site's baked content.js/i18n value for that language
     * (fail-open). SEO meta is short text, unlike WebsitePage's rich-text
     * body, so the max length is much smaller.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'array'],
            'value.en' => ['nullable', 'string', 'max:300'],
            'value.id' => ['nullable', 'string', 'max:300'],
            'value.ja' => ['nullable', 'string', 'max:300'],
            'value.ko' => ['nullable', 'string', 'max:300'],
            'value.zh' => ['nullable', 'string', 'max:300'],
        ];
    }
}
