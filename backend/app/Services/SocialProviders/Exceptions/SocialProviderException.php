<?php

namespace App\Services\SocialProviders\Exceptions;

use Exception;

class SocialProviderException extends Exception
{
    public static function unsupportedPlatform(string $platform): self
    {
        return new self("Unsupported social platform: {$platform}");
    }

    public static function missingImplementation(string $platform, string $driver): self
    {
        return new self("No provider implementation for [{$platform}] with driver [{$driver}].");
    }

    public static function capabilityNotSupported(string $platform, string $capability): self
    {
        return new self("Platform [{$platform}] does not support capability [{$capability}].");
    }

    public static function invalidAccount(string $platform, string $reason): self
    {
        return new self("Invalid {$platform} account: {$reason}");
    }

    public static function networkError(string $platform, string $reason): self
    {
        return new self("Network error publishing to {$platform}: {$reason}");
    }

    public static function configurationError(string $platform, string $reason): self
    {
        return new self("{$platform} configuration error: {$reason}");
    }
}
