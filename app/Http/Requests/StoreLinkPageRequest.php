<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLinkPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
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
                'unique:link_pages,slug',
                function ($attribute, $value, $fail) {
                    $existsInUsers = User::where('username', $value)->exists();
                    $existsInProjects = Project::where('username', $value)->exists();
                    $existsInShortLinks = ShortLink::where('slug', $value)->exists();

                    if ($existsInUsers || $existsInProjects || $existsInShortLinks) {
                        $fail('This slug is already taken.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', 'string', 'in:public,unlisted'],
            'more_details' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'og_image' => ['nullable', 'string'],
            'og_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'The slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'slug.regex' => 'Slug can only contain letters, numbers, and hyphens.',
            'title.required' => 'The title is required.',
            'visibility.in' => 'Visibility must be public or unlisted.',
        ];
    }
}
