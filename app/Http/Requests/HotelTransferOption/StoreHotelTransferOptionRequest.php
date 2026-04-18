<?php

namespace App\Http\Requests\HotelTransferOption;

use App\Enums\TransferDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreHotelTransferOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hotels.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'direction' => ['required', new Enum(TransferDirection::class)],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'max_pax' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
