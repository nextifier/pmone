<?php

namespace App\Rules;

use App\Support\PaymentChannels;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KnownPaymentChannel implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PaymentChannels::isValid($value)) {
            $fail('The selected payment channel is not supported.');
        }
    }
}
