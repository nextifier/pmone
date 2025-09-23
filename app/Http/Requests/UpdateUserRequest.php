<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('users.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username,'.$user->id],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
            'visibility' => ['sometimes', 'in:public,private'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name', function ($attribute, $value, $fail) {
                if ($value === 'master' && ! $this->user()->hasRole('master')) {
                    $fail('Only master users can assign the master role.');
                }
            }],
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
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username can only contain letters, numbers, dots, and underscores.',
            'password.min' => 'Password must be at least 8 characters long.',
            'birth_date.before' => 'Birth date must be before today.',
            'gender.in' => 'Please select a valid gender option.',
            'status.in' => 'Please select a valid status.',
            'visibility.in' => 'Please select a valid visibility option.',
            'roles.*.exists' => 'One or more selected roles do not exist.',
        ];
    }
}
