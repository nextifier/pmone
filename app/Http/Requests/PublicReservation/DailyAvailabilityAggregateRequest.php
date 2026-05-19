<?php

namespace App\Http\Requests\PublicReservation;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DailyAvailabilityAggregateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));

            if ($start->diffInDays($end) > 92) {
                $v->errors()->add('end_date', 'Range maksimum 92 hari.');
            }
        });
    }
}
