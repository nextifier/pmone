<?php

namespace App\Http\Requests;

use App\Enums\ContactStatus;
use App\Support\InputNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('emails') && is_array($this->emails)) {
            $this->merge(['emails' => InputNormalizer::emailList($this->emails)]);
        }

        if ($this->has('phones') && is_array($this->phones)) {
            $this->merge(['phones' => InputNormalizer::phoneList($this->phones)]);
        }

        if ($this->has('name') && is_string($this->name)) {
            $this->merge(['name' => InputNormalizer::personName($this->name)]);
        }

        foreach (['job_title', 'company_name'] as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                $this->merge([$field => InputNormalizer::orgName($this->$field)]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'emails' => ['nullable', 'array'],
            'emails.*' => [Email::default(), 'max:255'],
            'phones' => ['nullable', 'array'],
            'phones.*' => ['string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:500'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'max:50'],
            'more_details' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', ContactStatus::values())],
            'contact_types' => ['nullable', 'array'],
            'contact_types.*' => ['string', 'max:100'],
            'business_categories' => ['nullable', 'array'],
            'business_categories.*' => ['string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ];
    }
}
