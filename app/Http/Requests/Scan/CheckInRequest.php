<?php

namespace App\Http\Requests\Scan;

use App\Enums\Ticketing\ScanAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scan.check_in') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'action' => ['sometimes', Rule::in(ScanAction::values())],
        ];
    }
}
