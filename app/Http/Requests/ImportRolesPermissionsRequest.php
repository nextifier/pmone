<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRolesPermissionsRequest extends FormRequest
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
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'max:255'],
            'roles' => ['required', 'array'],
            'roles.*' => ['array'],
            'roles.*.*' => ['string', 'max:255'],
            'preview' => ['boolean'],
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
            'permissions.required' => 'Permissions array is required.',
            'permissions.array' => 'Permissions must be an array.',
            'permissions.*.string' => 'Each permission must be a string.',
            'roles.required' => 'Roles object is required.',
            'roles.array' => 'Roles must be an object.',
            'roles.*.array' => 'Each role must have an array of permissions.',
            'roles.*.*.string' => 'Each permission in a role must be a string.',
        ];
    }
}
