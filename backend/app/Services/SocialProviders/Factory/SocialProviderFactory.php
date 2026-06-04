<?php

namespace App\Services\SocialProviders\Factory;

use App\Models\SocialAccount;
use App\Services\SocialProviders\Contracts\SocialProviderInterface;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Contracts\Container\Container;

class SocialProviderFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(string $platform, SocialAccount $account): SocialProviderInterface
    {
        $platform = strtolower($platform);

        if ($account->provider !== $platform) {
            throw SocialProviderException::invalidAccount(
                $platform,
                "Account provider [{$account->provider}] does not match requested platform.",
            );
        }

        $driver = config("social_providers.drivers.{$platform}", 'fake');
        $class = config("social_providers.implementations.{$driver}.{$platform}");

        if ($class === null || ! class_exists($class)) {
            throw SocialProviderException::missingImplementation($platform, (string) $driver);
        }

        $provider = $this->container->make($class, [
            'account' => $account,
        ]);

        if (! $provider instanceof SocialProviderInterface) {
            throw SocialProviderException::missingImplementation($platform, (string) $driver);
        }

        return $provider;
    }

    public function supports(string $platform): bool
    {
        $platform = strtolower($platform);

        return config("social_providers.drivers.{$platform}") !== null;
    }

    /**
     * @return list<string>
     */
    public function supportedPlatforms(): array
    {
        return array_keys(config('social_providers.drivers', []));
    }
}
