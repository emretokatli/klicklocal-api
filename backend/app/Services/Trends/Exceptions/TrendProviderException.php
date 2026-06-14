<?php

namespace App\Services\Trends\Exceptions;

use Exception;

class TrendProviderException extends Exception
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self("Unsupported trend driver: {$driver}");
    }

    public static function missingImplementation(string $driver): self
    {
        return new self("No trend provider implementation for driver [{$driver}].");
    }

    public static function capabilityNotSupported(string $driver, string $capability): self
    {
        return new self("Trend driver [{$driver}] does not support capability [{$capability}].");
    }
}
