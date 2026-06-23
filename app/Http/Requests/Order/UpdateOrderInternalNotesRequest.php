<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderInternalNotesRequest extends FormRequest
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
            'internal_notes' => ['nullable', 'string', 'max:50000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.internal_notes' => ['nullable', 'string', 'max:50000'],
        ];
    }
}
