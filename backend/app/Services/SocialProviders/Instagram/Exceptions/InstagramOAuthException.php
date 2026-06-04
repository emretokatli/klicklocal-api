<?php

namespace App\Services\SocialProviders\Instagram\Exceptions;

use Exception;

class InstagramOAuthException extends Exception
{
    public static function providerDisabled(): self
    {
        return new self('Instagram Business Login is disabled.');
    }

    public static function missingConfiguration(string $field): self
    {
        return new self("Instagram OAuth is not configured: missing {$field}.");
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
        return new self('Instagram authorization was cancelled.');
    }

    public static function tokenExchangeFailed(string $message): self
    {
        return new self("Instagram token exchange failed: {$message}");
    }

    public static function apiError(string $message, ?int $code = null): self
    {
        $suffix = $code !== null ? " (HTTP {$code})" : '';

        return new self("Instagram API error: {$message}{$suffix}");
    }

    public static function networkFailure(string $message): self
    {
        return new self("Instagram network error: {$message}");
    }
}
