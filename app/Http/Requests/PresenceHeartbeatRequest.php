<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PresenceHeartbeatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The route is already gated by auth:sanctum; any authenticated user may
     * report their own presence.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:500', 'starts_with:/'],
            'title' => ['nullable', 'string', 'max:255'],
            'navigation' => ['required', 'boolean'],
        ];
    }
}
