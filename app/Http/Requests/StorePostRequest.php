<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Accept legacy plain-string payloads for the translatable fields by
     * coercing them into the English locale.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['title', 'excerpt', 'content', 'meta_title', 'meta_description'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = ['en' => $this->input($field) ?: null];
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Posts are authored Indonesian-first, so no single locale is mandatory;
     * title and content just need at least one filled language.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                foreach (['title', 'content'] as $field) {
                    $values = $this->input($field);

                    if (! is_array($values)) {
                        continue;
                    }

                    $hasValue = collect($values)->contains(
                        fn ($value) => is_string($value) && trim($value) !== ''
                    );

                    if (! $hasValue) {
                        $validator->errors()->add($field, "The post {$field} is required in at least one language.");
                    }
                }
            },
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt' => ['nullable', 'array'],
            'excerpt.*' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string'],
            'content_format' => ['sometimes', 'string', 'in:html,markdown,lexical'],
            'meta_title' => ['nullable', 'array'],
            'meta_title.*' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:draft,published,scheduled,archived'],
            'visibility' => ['sometimes', 'string', 'in:public,private,members_only'],
            'published_at' => ['nullable', 'date'],
            'featured' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'source' => ['sometimes', 'string', 'in:native,ghost,canvas'],
            'source_id' => ['nullable', 'string', 'max:255'],

            // Relationships
            'author_ids' => ['sometimes', 'array'],
            'author_ids.*' => ['exists:users,id'],

            // Authors (alternative to simple author_ids)
            'authors' => ['sometimes', 'array'],
            'authors.*.user_id' => ['required', 'exists:users,id'],
            'authors.*.order' => ['sometimes', 'integer', 'min:0'],

            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],

            // Media uploads
            'tmp_featured_image' => ['nullable', 'string'],
            'delete_featured_image' => ['nullable', 'boolean'],
            'featured_image_caption' => ['nullable', 'string', 'max:500'],
            'tmp_og_image' => ['nullable', 'string'],
            'delete_og_image' => ['nullable', 'boolean'],
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
            'title.required' => 'The post title is required.',
            'title.*.max' => 'Title must not exceed 255 characters.',
            'slug.unique' => 'This slug is already taken by another post.',
            'content.required' => 'Post content is required.',
            'content_format.in' => 'Content format must be html, markdown, or lexical.',
            'status.in' => 'Status must be draft, published, scheduled, or archived.',
            'visibility.in' => 'Visibility must be public, private, or members_only.',
            'published_at.date' => 'Published date must be a valid date.',
            'author_ids.*.exists' => 'One or more selected authors do not exist.',
        ];
    }
}
