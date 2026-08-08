<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePostRequest extends FormRequest
{
    /**
     * Mirrors StorePostRequest: a hand-typed slug is the only path a space or a
     * capital has into the column, and both have already reached production.
     * See the note there for what that cost.
     */
    protected function normaliseSlug(): void
    {
        $slug = $this->input('slug');

        if (is_string($slug) && trim($slug) !== '') {
            $this->merge(['slug' => Str::slug($slug)]);
        }
    }

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

        $this->normaliseSlug();
    }

    /**
     * Posts are authored Indonesian-first, so no single locale is mandatory;
     * when title or content is sent it needs at least one filled language.
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
        $postId = $this->route('post')?->id;

        return [
            'title' => ['sometimes', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:posts,slug,'.$postId],
            'excerpt' => ['nullable', 'array'],
            'excerpt.*' => ['nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'array'],
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
            'title.*.max' => 'Title must not exceed 255 characters.',
            'slug.unique' => 'This slug is already taken by another post.',
            'content_format.in' => 'Content format must be html, markdown, or lexical.',
            'status.in' => 'Status must be draft, published, scheduled, or archived.',
            'visibility.in' => 'Visibility must be public, private, or members_only.',
            'published_at.date' => 'Published date must be a valid date.',
            'author_ids.*.exists' => 'One or more selected authors do not exist.',
        ];
    }
}
