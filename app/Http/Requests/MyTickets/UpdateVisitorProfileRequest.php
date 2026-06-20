<?php

namespace App\Http\Requests\MyTickets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,other'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_matching_opt_in' => ['sometimes', 'boolean'],
        ];
    }
}
