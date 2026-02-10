<?php

namespace App\Http\Requests;

use App\Models\ShortLink;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
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
            'username' => ['sometimes', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username,'.$user->id, function ($attribute, $value, $fail) {
                if ($value && ShortLink::where('slug', $value)->exists()) {
                    $fail('This username is already in use as a short link slug.');
                }
            }],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'url', 'max:500'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
            'visibility' => ['sometimes', 'in:public,private'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name', function ($attribute, $value, $fail) {
                if ($value === 'master' && ! $this->user()->hasRole('master')) {
                    $fail('Only master users can assign the master role.');
                }
            }],
            'tmp_profile_image' => ['nullable', 'string', 'regex:/^tmp-[a-zA-Z0-9._]+$/'],
            'tmp_cover_image' => ['nullable', 'string', 'regex:/^tmp-[a-zA-Z0-9._]+$/'],
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
            'links.*.label.required' => 'Link label is required.',
            'links.*.label.max' => 'Link label must not exceed 100 characters.',
            'links.*.url.required' => 'Link URL is required.',
            'links.*.url.url' => 'Please enter a valid URL.',
            'links.*.url.max' => 'Link URL must not exceed 500 characters.',
        ];
    }
}
