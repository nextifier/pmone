<?php

namespace App\Http\Requests\Shaders;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConvertShapeSdfRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['master', 'admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:512',
                'extensions:svg,png',
                'mimetypes:image/svg+xml,image/png,text/plain,text/xml,application/xml',
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose an SVG or PNG file.',
            'file.extensions' => 'The logo must be an SVG or PNG file.',
            'file.mimetypes' => 'The logo must be an SVG or PNG file.',
            'file.max' => 'The logo must not be larger than 512 KB.',
        ];
    }
}
