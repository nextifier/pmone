<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects email domains that can never belong to a real recipient: the
 * RFC 2606/6761 reserved names people type as filler (example.com, .test) and
 * the common disposable inbox providers. Every send to one of these is a
 * guaranteed bounce, and bounces damage the whole account's sender reputation.
 *
 * Format validation is not this rule's job - pair it with the "email" rule,
 * which is what Email::defaults() does.
 */
class AllowedEmailDomain implements ValidationRule
{
    /**
     * Matched exactly and as a suffix, so sub.example.com is also caught.
     *
     * @var list<string>
     */
    private const BLOCKED_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
        'example.edu',
        'test.com',
        'mailinator.com',
        'yopmail.com',
        'guerrillamail.com',
        '10minutemail.com',
        'tempmail.com',
        'temp-mail.org',
        'trashmail.com',
        'sharklasers.com',
        'getnada.com',
        'dispostable.com',
        'maildrop.cc',
    ];

    /**
     * Reserved top-level domains that never resolve to a real mailbox.
     *
     * @var list<string>
     */
    private const BLOCKED_TLDS = [
        'test',
        'example',
        'invalid',
        'localhost',
        'local',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $atPosition = strrpos($value, '@');

        if ($atPosition === false) {
            return;
        }

        $domain = strtolower(substr($value, $atPosition + 1));

        if ($domain === '') {
            return;
        }

        $dotPosition = strrpos($domain, '.');
        $tld = $dotPosition === false ? $domain : substr($domain, $dotPosition + 1);

        if (in_array($tld, self::BLOCKED_TLDS, true)) {
            $fail('The :attribute must use a real email domain.');

            return;
        }

        foreach (self::BLOCKED_DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                $fail('The :attribute must use a real email domain.');

                return;
            }
        }
    }
}
