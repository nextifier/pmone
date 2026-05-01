<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('announcements.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'type' => ['sometimes', 'string', 'in:info,warning,success,error,marketing'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
            'is_global' => ['sometimes', 'boolean'],
            'is_dismissible' => ['sometimes', 'boolean'],
            'order_column' => ['nullable', 'integer', 'min:0'],

            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],

            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'exists:roles,name'],

            'cta_actions' => ['nullable', 'array'],
            'cta_actions.*.label' => ['required_with:cta_actions', 'string', 'max:100'],
            'cta_actions.*.url' => ['required_with:cta_actions', 'string', 'max:500'],
            'cta_actions.*.style' => ['required_with:cta_actions', 'string', 'in:link,button-primary,button-outline'],
            'cta_actions.*.icon' => ['nullable', 'string', 'max:100'],

            'more_details' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],

            'target_user_ids' => ['nullable', 'array'],
            'target_user_ids.*' => ['integer', 'exists:users,id'],
            'target_event_ids' => ['nullable', 'array'],
            'target_event_ids.*' => ['integer', 'exists:events,id'],
            'target_project_ids' => ['nullable', 'array'],
            'target_project_ids.*' => ['integer', 'exists:projects,id'],

            'tmp_image' => ['nullable', 'string'],
            'delete_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Announcement title is required.',
            'type.in' => 'Type must be info, warning, success, error, or marketing.',
            'status.in' => 'Status must be draft, published, or archived.',
            'end_time.after_or_equal' => 'End time must be after or equal to start time.',
            'cta_actions.*.style.in' => 'CTA style must be link, button-primary, or button-outline.',
        ];
    }
}
