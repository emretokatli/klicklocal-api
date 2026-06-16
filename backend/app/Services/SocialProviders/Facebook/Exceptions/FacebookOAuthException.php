<?php

namespace App\Services\SocialProviders\Facebook\Exceptions;

use Exception;

class FacebookOAuthException extends Exception
{
    public static function providerDisabled(): self
    {
        return new self('Facebook Login is disabled.');
    }

    public static function missingConfiguration(string $field): self
    {
        return new self("Facebook OAuth is not configured: missing {$field}.");
    }

    public static function invalidState(): self
    {
        return new self('Invalid or unknown OAuth state.');
    }

    public static function expiredState(): self
    {
        return new self('OAuth state has expired. Please try connecting again.');
    }

    public static function userDenied(): self
    {
        return new self('Facebook authorization was cancelled.');
    }

    public static function tokenExchangeFailed(string $message): self
    {
        return new self("Facebook token exchange failed: {$message}");
    }

    public static function noPages(): self
    {
        return new self('No Facebook Pages found for this account. You must manage at least one Page.');
    }

    public static function apiError(string $message, ?int $code = null): self
    {
        $suffix = $code !== null ? " (HTTP {$code})" : '';

        return new self("Facebook API error: {$message}{$suffix}");
    }

    public static function networkFailure(string $message): self
    {
        return new self("Facebook network error: {$message}");
    }
}
