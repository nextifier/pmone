<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class HoneypotPassed implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Minimum time in seconds that must pass before submission is valid.
     * This helps prevent automated submissions that happen too quickly.
     */
    protected int $minimumTimeInSeconds;

    /**
     * The honeypot field name that should remain empty.
     */
    protected string $honeypotFieldName;

    /**
     * The timestamp field name for time-based validation.
     */
    protected string $timestampFieldName;

    public function __construct(
        int $minimumTimeInSeconds = 2,
        string $honeypotFieldName = 'website',
        string $timestampFieldName = '_token_time'
    ) {
        $this->minimumTimeInSeconds = $minimumTimeInSeconds;
        $this->honeypotFieldName = $honeypotFieldName;
        $this->timestampFieldName = $timestampFieldName;
    }

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if honeypot field is filled (bots typically fill all fields)
        $honeypotValue = $this->data[$this->honeypotFieldName] ?? null;
        if (! empty($honeypotValue)) {
            // Log the attempt for monitoring
            logger()->warning('Honeypot triggered - bot detected', [
                'honeypot_field' => $this->honeypotFieldName,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $fail('Form submission failed. Please try again.');

            return;
        }

        // Check time-based validation
        $submittedTimestamp = $this->data[$this->timestampFieldName] ?? null;
        if ($submittedTimestamp) {
            $decodedTimestamp = $this->decodeTimestamp($submittedTimestamp);

            if ($decodedTimestamp) {
                $elapsedTime = time() - $decodedTimestamp;

                // If form was submitted too quickly, it's likely a bot
                if ($elapsedTime < $this->minimumTimeInSeconds) {
                    logger()->warning('Honeypot time check failed - submission too fast', [
                        'elapsed_seconds' => $elapsedTime,
                        'minimum_required' => $this->minimumTimeInSeconds,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);

                    $fail('Form submission failed. Please try again.');

                    return;
                }

                // If timestamp is more than 24 hours old, reject (stale token)
                if ($elapsedTime > 86400) {
                    $fail('Form has expired. Please refresh and try again.');

                    return;
                }
            }
        }
    }

    /**
     * Decode the obfuscated timestamp.
     */
    protected function decodeTimestamp(string $encoded): ?int
    {
        try {
            $decoded = base64_decode($encoded, true);
            if ($decoded === false) {
                return null;
            }

            // Extract timestamp from obfuscated string (format: random_timestamp_random)
            $parts = explode('_', $decoded);
            if (count($parts) >= 2) {
                return (int) $parts[1];
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Generate an encoded timestamp for frontend use.
     */
    public static function generateTimestampToken(): string
    {
        $timestamp = time();
        $random1 = bin2hex(random_bytes(4));
        $random2 = bin2hex(random_bytes(4));

        return base64_encode("{$random1}_{$timestamp}_{$random2}");
    }
}
