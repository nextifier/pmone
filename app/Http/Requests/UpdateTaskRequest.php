<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', Task::allowedStatuses())],
            'priority' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', Task::allowedPriorities())],
            'complexity' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', Task::allowedComplexities())],
            'visibility' => ['sometimes', 'string', 'in:'.implode(',', Task::allowedVisibilities())],

            // Shared users (required if visibility is 'shared')
            'shared_user_ids' => [
                'sometimes',
                'required_if:visibility,'.Task::VISIBILITY_SHARED,
                'array',
            ],
            'shared_user_ids.*' => ['exists:users,id'],
            'shared_roles' => ['sometimes', 'array'],
            'shared_roles.*' => ['string', 'in:'.implode(',', Task::allowedSharedRoles())],

            // Assignments
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'project_id' => ['sometimes', 'nullable', 'exists:projects,id'],

            // Timestamps
            'estimated_start_at' => ['sometimes', 'nullable', 'date'],
            'estimated_completion_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:estimated_start_at',
            ],

            // Media uploads (TipTap description images)
            'description_images' => ['sometimes', 'array'],
            'description_images.*' => ['string'],

            // Order column
            'order_column' => ['sometimes', 'integer', 'min:0'],
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
            'title.max' => 'Task title cannot exceed 255 characters.',

            'description.max' => 'Description is too long.',

            'status.in' => 'Invalid status. Must be one of: '.implode(', ', Task::allowedStatuses()),
            'priority.in' => 'Invalid priority. Must be one of: '.implode(', ', Task::allowedPriorities()),
            'complexity.in' => 'Invalid complexity. Must be one of: '.implode(', ', Task::allowedComplexities()),
            'visibility.in' => 'Invalid visibility. Must be one of: '.implode(', ', Task::allowedVisibilities()),

            'shared_user_ids.required_if' => 'You must select at least one user when visibility is set to shared.',
            'shared_user_ids.*.exists' => 'One or more selected users do not exist.',
            'shared_roles.*.in' => 'Invalid shared role. Must be viewer or editor.',

            'estimated_completion_at.after_or_equal' => 'Estimated completion time must be after or equal to estimated start time.',

            'assignee_id.exists' => 'Selected assignee does not exist.',
            'project_id.exists' => 'Selected project does not exist.',
        ];
    }
}
