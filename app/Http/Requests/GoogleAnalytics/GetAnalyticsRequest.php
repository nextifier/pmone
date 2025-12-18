<?php

namespace App\Http\Requests\GoogleAnalytics;

use Illuminate\Foundation\Http\FormRequest;

class GetAnalyticsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow users with analytics.view permission OR master/admin roles
        return $this->user()->hasPermissionTo('analytics.view')
            || $this->user()->hasRole(['master', 'admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['string', 'exists:ga_properties,property_id'],
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'period' => ['nullable', 'string', 'in:today,yesterday,this_week,last_week,this_month,last_month,this_year'],
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
            'property_ids.*.exists' => 'One or more selected properties do not exist.',
            'start_date.before_or_equal' => 'Start date must be before or equal to end date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'end_date.before_or_equal' => 'End date cannot be in the future.',
            'days.max' => 'Maximum allowed period is 365 days.',
        ];
    }
}
