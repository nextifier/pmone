<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AutosavePostRequest extends FormRequest
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
     * For autosave, we're more lenient - title and content can be empty/incomplete
     * since the user is still working on the post.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_id' => ['nullable', 'exists:posts,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'content_format' => ['sometimes', 'string', 'in:html,markdown,lexical'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:draft,published,scheduled,archived'],
            'visibility' => ['sometimes', 'string', 'in:public,private,members_only'],
            'published_at' => ['nullable', 'date'],
            'featured' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],

            // Media - store as JSON for autosave
            'tmp_media' => ['sometimes', 'array'],
            'tmp_media.featured_image' => ['nullable', 'string'],
            'tmp_media.og_image' => ['nullable', 'string'],
            'tmp_media.featured_image_caption' => ['nullable', 'string', 'max:500'],

            // Tags and authors
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'authors' => ['sometimes', 'array'],
            'authors.*.user_id' => ['required', 'exists:users,id'],
            'authors.*.order' => ['sometimes', 'integer', 'min:0'],
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
            'post_id.exists' => 'The post you are trying to autosave does not exist.',
            'title.max' => 'Title must not exceed 255 characters.',
            'content_format.in' => 'Content format must be html, markdown, or lexical.',
            'status.in' => 'Status must be draft, published, scheduled, or archived.',
            'visibility.in' => 'Visibility must be public, private, or members_only.',
            'published_at.date' => 'Published date must be a valid date.',
        ];
    }
}
