<?php

namespace App\Http\Requests;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'content_format' => ['sometimes', 'string', 'in:html,markdown,lexical'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
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

            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['exists:categories,id'],
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
            'title.max' => 'Title must not exceed 255 characters.',
            'slug.unique' => 'This slug is already taken by another post.',
            'content.required' => 'Post content is required.',
            'content_format.in' => 'Content format must be html, markdown, or lexical.',
            'status.in' => 'Status must be draft, published, scheduled, or archived.',
            'visibility.in' => 'Visibility must be public, private, or members_only.',
            'published_at.date' => 'Published date must be a valid date.',
            'author_ids.*.exists' => 'One or more selected authors do not exist.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.',
        ];
    }
}
