<?php

namespace App\Services\Trends\Factory;

use App\Services\Trends\Contracts\TrendProviderInterface;
use App\Services\Trends\Exceptions\TrendProviderException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves a trend provider for the configured driver.
 *
 * Mirrors App\Services\SocialProviders\Factory\SocialProviderFactory: the driver
 * is read from config, the implementation class is resolved through the container,
 * and a missing/invalid binding throws a TrendProviderException.
 */
class TrendProviderFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(?string $driver = null): TrendProviderInterface
    {
        $driver = strtolower($driver ?? (string) config('trends.driver', 'fake'));

        $class = config("trends.implementations.{$driver}");

        if ($class === null || ! class_exists($class)) {
            throw TrendProviderException::missingImplementation($driver);
        }

        $provider = $this->container->make($class);

        if (! $provider instanceof TrendProviderInterface) {
            throw TrendProviderException::missingImplementation($driver);
        }

        return $provider;
    }

    public function supports(string $driver): bool
    {
        return config('trends.implementations.'.strtolower($driver)) !== null;
    }

    /**
     * @return list<string>
     */
    public function supportedDrivers(): array
    {
        return array_keys(config('trends.implementations', []));
    }
}
