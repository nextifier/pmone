<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['master', 'admin'])
            || $this->user()?->can('orders.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand_event_id' => ['required', 'integer', 'exists:brand_event,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.event_product_id' => ['required', 'integer', 'exists:event_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'promo_code' => ['nullable', 'string', 'max:60'],
            'send_confirmation_email' => ['sometimes', 'boolean'],
        ];
    }
}
