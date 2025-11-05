<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShortLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._\-]+$/',
                'unique:short_links,slug',
                function ($attribute, $value, $fail) {
                    // Check if slug conflicts with existing usernames or project usernames
                    $existsInUsers = \App\Models\User::where('username', $value)->exists();
                    $existsInProjects = \App\Models\Project::where('username', $value)->exists();

                    if ($existsInUsers || $existsInProjects) {
                        $fail('This slug is already taken by a user or project.');
                    }
                },
            ],
            'destination_url' => ['required', 'url', 'max:2000'],
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
