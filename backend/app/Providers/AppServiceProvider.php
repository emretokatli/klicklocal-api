<?php

namespace App\Providers;

use App\Contracts\Post\PostPublisherInterface;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\FakeOpenAiClient;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\WebsiteAnalysisService;
use App\Services\Post\PostPublishingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostPublisherInterface::class, PostPublishingService::class);

        $this->app->bind(OpenAiClientInterface::class, function ($app): OpenAiClientInterface {
            $config = $app['config']->get('services.openai');

            if (($config['driver'] ?? 'fake') === 'fake') {
                return new FakeOpenAiClient;
            }

            return new OpenAiClient(
                apiKey: (string) ($config['key'] ?? ''),
                model: (string) ($config['model'] ?? 'gpt-5'),
                baseUrl: (string) ($config['base_url'] ?? 'https://api.openai.com/v1'),
                timeout: (int) ($config['timeout'] ?? 60),
            );
        });

        $this->app->singleton(WebsiteAnalysisService::class, function ($app): WebsiteAnalysisService {
            $config = $app['config']->get('services.openai');

            return new WebsiteAnalysisService(
                apiKey: (string) ($config['key'] ?? ''),
                driver: (string) ($config['driver'] ?? 'fake'),
                model: (string) ($config['model'] ?? 'gpt-5'),
                baseUrl: (string) ($config['base_url'] ?? 'https://api.openai.com/v1'),
                timeout: (int) ($config['timeout'] ?? 60),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
