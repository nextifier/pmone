<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * No locale is required: unlike Faq (which needs at least an English
     * answer to be useful), a legal page override is optional per-locale by
     * design - an admin may clear a locale back to blank to fall back to the
     * site's baked copy for that language (fail-open). Likewise
     * `last_updated_at` is optional: an unset date falls back to the legacy
     * project-level terms date on the public site.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'array'],
            'body.en' => ['nullable', 'string', 'max:50000'],
            'body.id' => ['nullable', 'string', 'max:50000'],
            'body.ja' => ['nullable', 'string', 'max:50000'],
            'body.ko' => ['nullable', 'string', 'max:50000'],
            'body.zh' => ['nullable', 'string', 'max:50000'],
            'last_updated_at' => ['nullable', 'date'],
        ];
    }
}
