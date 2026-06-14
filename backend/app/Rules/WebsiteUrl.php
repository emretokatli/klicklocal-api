<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a user-supplied website address. Accepts inputs without a scheme
 * (the callers prepend https://) but rejects non-URL garbage, credentials,
 * non-http(s) schemes, and hosts without a valid domain shape.
 */
class WebsiteUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('Bitte gib eine gültige Website-Adresse an.');

            return;
        }

        $candidate = trim($value);

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $candidate) === 1) {
            if (preg_match('#^https?://#i', $candidate) !== 1) {
                $fail('Bitte gib eine gültige Website-Adresse an.');

                return;
            }
        } else {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);

        if (
            $parts === false
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            $fail('Bitte gib eine gültige Website-Adresse an.');

            return;
        }

        $host = $parts['host'];

        // Allow IDN hosts (müller.de) by converting to punycode first.
        if (function_exists('idn_to_ascii')) {
            $converted = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($converted)) {
                $host = $converted;
            }
        }

        $isIp = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
        $isDomain = preg_match(
            '/^([a-z0-9\x{00a1}-\x{ffff}]([a-z0-9\x{00a1}-\x{ffff}-]*[a-z0-9\x{00a1}-\x{ffff}])?\.)+[a-z\x{00a1}-\x{ffff}]{2,63}$/iu',
            $host,
        ) === 1;

        if (! $isIp && ! $isDomain) {
            $fail('Bitte gib eine gültige Website-Adresse an.');
        }
    }
}
