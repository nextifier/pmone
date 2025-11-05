<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShortLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('update', $this->route('shortLink'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shortLink = $this->route('shortLink');
        $shortLinkId = $shortLink->id;

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._\-]+$/',
                Rule::unique('short_links', 'slug')->ignore($shortLinkId),
                function ($attribute, $value, $fail) use ($shortLink) {
                    // Only validate against users/projects if slug is changing
                    if ($value !== $shortLink->slug) {
                        $existsInUsers = \App\Models\User::where('username', $value)->exists();
                        $existsInProjects = \App\Models\Project::where('username', $value)->exists();

                        if ($existsInUsers || $existsInProjects) {
                            $fail('This slug is already taken by a user or project.');
                        }
                    }
                },
            ],
            'destination_url' => ['sometimes', 'required', 'url', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'The short link slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'slug.regex' => 'Slug can only contain letters, numbers, dots, underscores, and hyphens.',
            'slug.max' => 'Slug must not exceed 255 characters.',
            'destination_url.required' => 'The destination URL is required.',
            'destination_url.url' => 'Please enter a valid URL.',
            'destination_url.max' => 'Destination URL must not exceed 2000 characters.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}
