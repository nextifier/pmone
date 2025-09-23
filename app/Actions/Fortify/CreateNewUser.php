<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'bio' => ['nullable', 'string', 'max:1000'],
            'links' => ['nullable', 'array'],
            'links.*' => ['url'],
            'visibility' => ['nullable', Rule::in(['public', 'private', 'limited'])],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'phone' => $input['phone'] ?? null,
            'birth_date' => $input['birth_date'] ?? null,
            'gender' => $input['gender'] ?? null,
            'bio' => $input['bio'] ?? null,
            'links' => $input['links'] ?? null,
            'visibility' => $input['visibility'] ?? 'public',
            'status' => 'active',
        ]);

        // Assign default role
        $user->assignRole('user');

        // Log activity
        activity()
            ->performedOn($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('User registered');

        return $user;
    }
}
