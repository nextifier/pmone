<?php

namespace App\Http\Requests;

use App\Models\LinkPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\-]+$/',
                'unique:short_links,slug',
                function ($attribute, $value, $fail) {
                    // Check if slug conflicts with existing usernames, project usernames, or link pages
                    $existsInUsers = User::where('username', $value)->exists();
                    $existsInProjects = Project::where('username', $value)->exists();
                    $existsInLinkPages = LinkPage::where('slug', $value)->exists();

                    if ($existsInUsers || $existsInProjects || $existsInLinkPages) {
                        $fail('This slug is already taken.');
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
            'slug.regex' => 'Slug can only contain letters, numbers, and hyphens.',
            'slug.max' => 'Slug must not exceed 255 characters.',
            'destination_url.required' => 'The destination URL is required.',
            'destination_url.url' => 'Please enter a valid URL.',
            'destination_url.max' => 'Destination URL must not exceed 2000 characters.',
            'is_active.boolean' => 'The is_active field must be true or false.',
        ];
    }
}
