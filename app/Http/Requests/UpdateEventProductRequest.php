<?php

namespace App\Http\Requests;

use App\Enums\BoothType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventProductRequest extends FormRequest
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
            'category' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'booth_types' => ['nullable', 'array'],
            'booth_types.*' => ['string', Rule::in(array_column(BoothType::cases(), 'value'))],
            'is_active' => ['sometimes', 'boolean'],
            'tmp_product_image' => ['nullable', 'string'],
            'delete_product_image' => ['nullable', 'boolean'],
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
            'name.max' => 'Product name cannot exceed 255 characters.',
            'price.min' => 'Product price cannot be negative.',
            'booth_types.*.in' => 'Invalid booth type specified.',
        ];
    }
}
